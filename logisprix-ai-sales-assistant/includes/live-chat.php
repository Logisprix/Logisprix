<?php
defined('ABSPATH') || exit;
/** Staging MVP: conversation inbox, human takeover, and appearance controls. */
final class Logisprix_AI_Live {
 const OPT='logisprix_ai_live_options';
 static function boot(){
  add_action('admin_menu',[__CLASS__,'menu']);
  add_action('admin_init',[__CLASS__,'settings']);
  add_action('rest_api_init',[__CLASS__,'routes']);
  add_shortcode('logisprix_ai_chat',[__CLASS__,'widget']);
  add_action('admin_enqueue_scripts',[__CLASS__,'assets']);
 }
 static function defaults(){return ['name'=>'Asistente Logisprix','welcome'=>'¡Hola! ¿En qué podemos ayudarte?','primary'=>'#73B735','secondary'=>'#333333'];}
 static function opts(){return wp_parse_args(get_option(self::OPT,[]),self::defaults());}
 static function settings(){
  register_setting('logisprix_ai_live',self::OPT,['sanitize_callback'=>function($v){
   $d=self::defaults();$o=[];
   foreach(['name','welcome'] as $k)$o[$k]=sanitize_text_field($v[$k]??$d[$k]);
   foreach(['primary','secondary'] as $k)$o[$k]=sanitize_hex_color($v[$k]??'')?:$d[$k];
   return $o;
  }]);
 }
 static function menu(){
  add_submenu_page('logisprix-ai','Conversaciones en directo','Conversaciones en directo','edit_posts','logisprix-ai-live',[__CLASS__,'inbox']);
  add_submenu_page('logisprix-ai','Diseño del chat','Diseño del chat','manage_options','logisprix-ai-design',[__CLASS__,'design']);
 }
 static function design(){
  if(!current_user_can('manage_options'))return;
  $o=self::opts();echo '<div class="wrap"><h1>Diseño del asistente</h1><form method="post" action="options.php">';
  settings_fields('logisprix_ai_live');
  foreach(['name'=>'Nombre','welcome'=>'Bienvenida','primary'=>'Color principal','secondary'=>'Color complementario'] as $k=>$label){
   echo '<p><label>'.esc_html($label).' <input name="'.esc_attr(self::OPT).'['.esc_attr($k).']" value="'.esc_attr($o[$k]).'" '.(in_array($k,['primary','secondary'],true)?'type="color"':'type="text" size="55"').'></label></p>';
  }
  submit_button();echo '</form></div>';
 }
 static function activate(){
  global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';
  $c=$wpdb->get_charset_collate();
  dbDelta("CREATE TABLE {$wpdb->prefix}logisprix_ai_conversations (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   token_hash char(64) NOT NULL,
   status varchar(20) NOT NULL DEFAULT 'ai',
   created_at datetime NOT NULL,
   updated_at datetime NOT NULL,
   PRIMARY KEY  (id),
   UNIQUE KEY token_hash (token_hash),
   KEY updated_at (updated_at)
  ) $c;");
  dbDelta("CREATE TABLE {$wpdb->prefix}logisprix_ai_messages (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   conversation_id bigint(20) unsigned NOT NULL,
   sender varchar(20) NOT NULL,
   body text NOT NULL,
   created_at datetime NOT NULL,
   PRIMARY KEY  (id),
   KEY conversation_id (conversation_id)
  ) $c;");
 }
 static function routes(){
  register_rest_route('logisprix-ai/v1','/session',['methods'=>'POST','callback'=>[__CLASS__,'session'],'permission_callback'=>'__return_true']);
  register_rest_route('logisprix-ai/v1','/conversation',['methods'=>'POST','callback'=>[__CLASS__,'visitor'],'permission_callback'=>'__return_true']);
  register_rest_route('logisprix-ai/v1','/inbox',['methods'=>'GET','callback'=>[__CLASS__,'list_inbox'],'permission_callback'=>[__CLASS__,'can_agent']]);
  register_rest_route('logisprix-ai/v1','/agent',['methods'=>'POST','callback'=>[__CLASS__,'agent'],'permission_callback'=>[__CLASS__,'can_agent']]);
 }
 static function can_agent(){return current_user_can('edit_posts');}
 static function limit($r){return Logisprix_AI_Sales::throttle($r);}
 static function session($r){
  $l=self::limit($r);if(is_wp_error($l))return $l;
  global $wpdb;$token=bin2hex(random_bytes(32));$now=current_time('mysql');
  $ok=$wpdb->insert($wpdb->prefix.'logisprix_ai_conversations',['token_hash'=>hash('sha256',$token),'status'=>'ai','created_at'=>$now,'updated_at'=>$now]);
  if(!$ok)return new WP_Error('storage','No se pudo iniciar la conversación',['status'=>500]);
  return ['id'=>$wpdb->insert_id,'token'=>$token,'status'=>'ai'];
 }
 static function conversation($id,$token){
  global $wpdb;
  if(!is_numeric($id)||!is_string($token)||!preg_match('/^[a-f0-9]{64}$/',$token))return null;
  return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}logisprix_ai_conversations WHERE id=%d AND token_hash=%s",(int)$id,hash('sha256',$token)));
 }
 static function messages($id,$after=0){
  global $wpdb;
  return $wpdb->get_results($wpdb->prepare("SELECT id,sender,body,created_at FROM {$wpdb->prefix}logisprix_ai_messages WHERE conversation_id=%d AND id>%d ORDER BY id ASC LIMIT 100",$id,$after),ARRAY_A);
 }
 static function add($id,$sender,$body){
  global $wpdb;
  $wpdb->insert($wpdb->prefix.'logisprix_ai_messages',['conversation_id'=>$id,'sender'=>$sender,'body'=>$body,'created_at'=>current_time('mysql')]);
  $wpdb->update($wpdb->prefix.'logisprix_ai_conversations',['updated_at'=>current_time('mysql')],['id'=>$id]);
 }
 static function visitor($r){
  $action=$r->get_param('action');
  if($action!=='poll'){$l=self::limit($r);if(is_wp_error($l))return $l;}
  $c=self::conversation($r->get_param('id'),$r->get_param('token'));
  if(!$c)return new WP_Error('forbidden','Sesión inválida',['status'=>403]);
  $after=max(0,(int)$r->get_param('after'));
  if($action==='send'){
   $body=sanitize_textarea_field($r->get_param('message'));
   if(!$body||mb_strlen($body)>1500)return new WP_Error('invalid','Mensaje inválido',['status'=>400]);
   self::add($c->id,'visitor',$body);
   if($c->status==='ai'){
    // Pass the visitor message into the existing grounded AI handler.
    $request=new WP_REST_Request('POST','/logisprix-ai/v1/chat');$request->set_param('message',$body);
    $reply=Logisprix_AI_Sales::chat($request);
    $fresh=self::conversation($r->get_param('id'),$r->get_param('token'));
    if(!is_wp_error($reply)&&$fresh&&$fresh->status==='ai'){
     self::add($c->id,'ai',$reply['answer']);
     if(!empty($reply['handoff']))self::set_status($c->id,'waiting');
    }elseif(is_wp_error($reply)&&$fresh&&$fresh->status==='ai')self::add($c->id,'system','No he podido responder ahora. Puedes solicitar atención comercial.');
   }
  }elseif($action==='human'){
   self::set_status($c->id,'waiting');self::add($c->id,'system','Has solicitado atención humana.');
  }elseif($action!=='poll')return new WP_Error('invalid','Acción inválida',['status'=>400]);
  $c=self::conversation($r->get_param('id'),$r->get_param('token'));
  return ['status'=>$c->status,'messages'=>self::messages($c->id,$after)];
 }
 static function set_status($id,$status){global $wpdb;$wpdb->update($wpdb->prefix.'logisprix_ai_conversations',['status'=>$status,'updated_at'=>current_time('mysql')],['id'=>$id]);}
 static function list_inbox($r){
  global $wpdb;$id=(int)$r->get_param('id');$after=max(0,(int)$r->get_param('after'));
  if($id){
   $c=$wpdb->get_row($wpdb->prepare("SELECT id,status,updated_at FROM {$wpdb->prefix}logisprix_ai_conversations WHERE id=%d",$id),ARRAY_A);
   if(!$c)return new WP_Error('missing','Conversación no encontrada',['status'=>404]);
   return ['conversation'=>$c,'messages'=>self::messages($id,$after)];
  }
  return $wpdb->get_results("SELECT id,status,updated_at FROM {$wpdb->prefix}logisprix_ai_conversations ORDER BY updated_at DESC LIMIT 100",ARRAY_A);
 }
 static function agent($r){
  $id=(int)$r->get_param('id');$action=$r->get_param('action');
  global $wpdb;$exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}logisprix_ai_conversations WHERE id=%d",$id));
  if(!$exists)return new WP_Error('missing','Conversación no encontrada',['status'=>404]);
  if($action==='take')self::set_status($id,'human');
  elseif($action==='release')self::set_status($id,'ai');
  elseif($action==='send'){
   $status=$wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}logisprix_ai_conversations WHERE id=%d",$id));
   if($status!=='human')return new WP_Error('invalid','Toma primero la conversación',['status'=>409]);
   $body=sanitize_textarea_field($r->get_param('message'));
   if(!$body||mb_strlen($body)>1500)return new WP_Error('invalid','Mensaje inválido',['status'=>400]);
   self::add($id,'agent',$body);
  }else return new WP_Error('invalid','Acción inválida',['status'=>400]);
  return ['ok'=>true];
 }
 static function assets($hook){if($hook!=='logisprix-ai_page_logisprix-ai-live')return;}
 static function inbox(){
  if(!self::can_agent())return;
  echo '<div class="wrap"><h1>Conversaciones en directo</h1><p>Actualización cada 4 segundos. No compartas datos sensibles en el chat.</p><div style="display:flex;gap:20px"><div style="width:230px"><h2>Conversaciones</h2><div id="lpx-inbox"></div></div><div style="flex:1"><h2 id="lpx-current">Selecciona una conversación</h2><div id="lpx-history" style="height:350px;overflow:auto;background:white;border:1px solid #ccc;padding:12px"></div><p><button class="button" id="lpx-take">Tomar conversación</button> <button class="button" id="lpx-release">Devolver a IA</button></p><textarea id="lpx-answer" rows="3" style="width:100%"></textarea><p><button class="button button-primary" id="lpx-send">Enviar respuesta</button></p></div></div></div>';
  $base=esc_url_raw(rest_url('logisprix-ai/v1/'));$nonce=wp_create_nonce('wp_rest');
  echo '<script>const LPX_BASE='.wp_json_encode($base).',LPX_NONCE='.wp_json_encode($nonce).';let lpxId=0;async function lpxReq(path,data){let r=await fetch(LPX_BASE+path,{method:data?"POST":"GET",headers:{"Content-Type":"application/json","X-WP-Nonce":LPX_NONCE},body:data?JSON.stringify(data):undefined});if(!r.ok)throw Error((await r.json()).message);return r.json()}async function lpxRefresh(){try{let list=await lpxReq("inbox");document.getElementById("lpx-inbox").replaceChildren(...list.map(c=>{let b=document.createElement("button");b.className="button";b.style.display="block";b.style.marginBottom="6px";b.textContent="#"+c.id+" · "+c.status;b.onclick=()=>{lpxId=c.id;lpxRefresh()};return b}));if(lpxId){let d=await lpxReq("inbox?id="+lpxId);document.getElementById("lpx-current").textContent="Conversación #"+lpxId+" · "+d.conversation.status;let h=document.getElementById("lpx-history");h.replaceChildren(...d.messages.map(m=>{let p=document.createElement("p");p.textContent=m.sender+": "+m.body;return p}))}}catch(e){document.getElementById("lpx-current").textContent=e.message}}async function lpxAction(action){if(!lpxId)return;try{await lpxReq("agent",{id:lpxId,action,message:document.getElementById("lpx-answer").value});if(action==="send")document.getElementById("lpx-answer").value="";await lpxRefresh()}catch(e){alert(e.message)}}document.getElementById("lpx-take").onclick=()=>lpxAction("take");document.getElementById("lpx-release").onclick=()=>lpxAction("release");document.getElementById("lpx-send").onclick=()=>lpxAction("send");lpxRefresh();setInterval(lpxRefresh,4000);</script>';
 }
 static function widget(){
  $o=self::opts();$id='lpx-'.wp_rand(100000,999999);$base=rest_url('logisprix-ai/v1/');
  ob_start();?>
  <section id="<?php echo esc_attr($id); ?>" style="max-width:480px;border:1px solid #ddd;border-radius:12px;overflow:hidden">
   <header style="background:<?php echo esc_attr($o['secondary']); ?>;color:#fff;padding:14px;font-weight:bold"><?php echo esc_html($o['name']); ?></header>
   <div style="padding:14px"><p><?php echo esc_html($o['welcome']); ?></p><div class="lpx-messages" aria-live="polite" style="height:250px;overflow:auto"></div>
   <form class="lpx-form"><textarea required maxlength="1500" style="width:100%"></textarea><button style="background:<?php echo esc_attr($o['primary']); ?>;color:#111;padding:10px;border:0">Enviar</button></form>
   <button type="button" class="lpx-human">Hablar con un comercial</button><button type="button" class="lpx-lead-toggle">Dejar mis datos</button><form class="lpx-lead-form" hidden><label>Nombre <input name="name" required maxlength="190"></label><label>Empresa <input name="company" maxlength="190"></label><label>Email <input type="email" name="email" required></label><label>Consulta <textarea name="question" maxlength="2000"></textarea></label><label><input type="checkbox" name="consent" required> Acepto la <a href="<?php echo esc_url(get_privacy_policy_url()); ?>">política de privacidad</a> para gestionar mi solicitud.</label><input name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px"><button>Enviar datos</button></form><p class="lpx-state" aria-live="polite"></p></div>
  </section>
  <script>(function(){const root=document.getElementById(<?php echo wp_json_encode($id); ?>),base=<?php echo wp_json_encode($base); ?>;let session=null,last=0,busy=false;const box=root.querySelector(".lpx-messages");async function req(path,data){let r=await fetch(base+path,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(data)}),v=await r.json();if(!r.ok)throw Error(v.message||"Error");return v}function show(v){root.querySelector(".lpx-state").textContent=v.status==="human"?"Te atiende un comercial":v.status==="waiting"?"Esperando atención comercial":"Atención automática con IA";v.messages.forEach(m=>{let p=document.createElement("p");p.textContent=m.sender+": "+m.body;box.appendChild(p);last=Math.max(last,Number(m.id))});box.scrollTop=box.scrollHeight}async function poll(){if(!session||busy)return;try{show(await req("conversation",{...session,action:"poll",after:last}))}catch(e){root.querySelector(".lpx-state").textContent=e.message}}async function init(){try{session=await req("session",{});poll();setInterval(poll,4000)}catch(e){root.querySelector(".lpx-state").textContent=e.message}}root.querySelector(".lpx-form").onsubmit=async e=>{e.preventDefault();if(!session||busy)return;let t=root.querySelector("textarea"),message=t.value.trim();if(!message)return;busy=true;try{show(await req("conversation",{...session,action:"send",message,after:last}));t.value=""}catch(e){root.querySelector(".lpx-state").textContent=e.message}finally{busy=false}};root.querySelector(".lpx-lead-toggle").onclick=()=>{root.querySelector(".lpx-lead-form").hidden=false};root.querySelector(".lpx-lead-form").onsubmit=async e=>{e.preventDefault();let form=e.currentTarget,d=Object.fromEntries(new FormData(form).entries());d.consent=form.querySelector("[name=consent]").checked?"on":"";try{let r=await req("lead",d);root.querySelector(".lpx-state").textContent=r.message;form.hidden=true;form.reset()}catch(err){root.querySelector(".lpx-state").textContent=err.message}};root.querySelector(".lpx-human").onclick=async()=>{if(!session)return;try{show(await req("conversation",{...session,action:"human",after:last}))}catch(e){root.querySelector(".lpx-state").textContent=e.message}};init()})()</script>
  <?php return ob_get_clean();
 }
}
register_activation_hook(dirname(__DIR__).'/logisprix-ai-sales-assistant.php',['Logisprix_AI_Live','activate']);
add_action('init',['Logisprix_AI_Live','boot'],20);
