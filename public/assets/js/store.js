import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.180.0/build/three.module.js';
const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const hero=document.querySelector('.hero-art');
if(hero&&!reduced){
 let renderer;
 try{
  const scene=new THREE.Scene();
  const camera=new THREE.PerspectiveCamera(38,hero.clientWidth/hero.clientHeight,.1,100);camera.position.z=8;
  renderer=new THREE.WebGLRenderer({alpha:true,antialias:true});renderer.setPixelRatio(Math.min(devicePixelRatio,1.6));renderer.setSize(hero.clientWidth,hero.clientHeight);
  renderer.domElement.setAttribute('aria-hidden','true');renderer.domElement.style.cssText='position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:1';hero.appendChild(renderer.domElement);
  const group=new THREE.Group();scene.add(group);
  const orbit=new THREE.Mesh(new THREE.TorusGeometry(2.1,.008,8,160),new THREE.MeshBasicMaterial({color:0xb18b77,transparent:true,opacity:.42}));orbit.rotation.x=1.12;orbit.rotation.y=-.28;group.add(orbit);
  const bead=new THREE.Mesh(new THREE.SphereGeometry(.055,20,20),new THREE.MeshStandardMaterial({color:0xe8cdb8,metalness:.45,roughness:.25}));bead.position.set(1.85,.55,.2);group.add(bead);
  scene.add(new THREE.HemisphereLight(0xffffff,0x8c6d5b,2));const light=new THREE.PointLight(0xffe1c7,22,20);light.position.set(-2,3,5);scene.add(light);
  const resize=()=>{camera.aspect=hero.clientWidth/hero.clientHeight;camera.updateProjectionMatrix();renderer.setSize(hero.clientWidth,hero.clientHeight)};addEventListener('resize',resize,{passive:true});
  const animate=()=>{if(!document.hidden){group.rotation.z+=.0018;bead.position.y=.55+Math.sin(performance.now()*.0008)*.08;renderer.render(scene,camera)}requestAnimationFrame(animate)};animate();
 }catch(e){if(renderer)renderer.dispose();console.info('3D enhancement unavailable; CSS artwork remains visible.')}
}
if(window.gsap&&!reduced){
 window.gsap.from('.hero-copy > *',{y:22,opacity:0,duration:.8,stagger:.12,ease:'power2.out'});
 window.gsap.from('.hero-art > *',{opacity:0,y:14,duration:1,stagger:.08,ease:'power2.out',delay:.25});
 window.gsap.utils.toArray('.category-card,.product-card').forEach(card=>{
  card.addEventListener('mouseenter',()=>window.gsap.to(card,{y:-5,duration:.25}));
  card.addEventListener('mouseleave',()=>window.gsap.to(card,{y:0,duration:.25}));
 });
}