import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.180.0/build/three.module.js';

const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function createRenderer(container){
 const renderer=new THREE.WebGLRenderer({alpha:true,antialias:true});
 renderer.setPixelRatio(Math.min(window.devicePixelRatio||1,1.5));
 renderer.setSize(container.clientWidth,container.clientHeight);
 renderer.outputColorSpace=THREE.SRGBColorSpace;
 renderer.domElement.setAttribute('aria-hidden','true');
 renderer.domElement.style.cssText='position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:1';
 container.appendChild(renderer.domElement);
 return renderer;
}

function makeBottle(scene,{x=0,y=0,scale=1,tint=0xd6b7a8}={}){
 const group=new THREE.Group();group.position.set(x,y,0);group.scale.setScalar(scale);scene.add(group);
 const glass=new THREE.MeshPhysicalMaterial({color:tint,metalness:.12,roughness:.2,transparent:true,opacity:.94,clearcoat:1,clearcoatRoughness:.12});
 const gold=new THREE.MeshStandardMaterial({color:0xb18b65,metalness:.78,roughness:.23});
 const label=new THREE.MeshStandardMaterial({color:0xf5e9dc,roughness:.8});
 const body=new THREE.Mesh(new THREE.CylinderGeometry(.62,.58,2.15,48,1,false),glass);body.position.y=-.1;group.add(body);
 const shoulder=new THREE.Mesh(new THREE.CylinderGeometry(.28,.58,.38,48),glass);shoulder.position.y=1.16;group.add(shoulder);
 const neck=new THREE.Mesh(new THREE.CylinderGeometry(.22,.22,.28,32),gold);neck.position.y=1.47;group.add(neck);
 const cap=new THREE.Mesh(new THREE.CylinderGeometry(.25,.25,.42,40),gold);cap.position.y=1.82;group.add(cap);
 const band=new THREE.Mesh(new THREE.CylinderGeometry(.625,.625,.055,48),gold);band.position.y=-.82;group.add(band);
 const plaque=new THREE.Mesh(new THREE.PlaneGeometry(.78,.42),label);plaque.position.set(0,-.12,.585);group.add(plaque);
 const line=new THREE.Mesh(new THREE.PlaneGeometry(.38,.018),gold);line.position.set(0,-.12,.592);group.add(line);
 return group;
}

function mountHero3D(container){
 let renderer;
 try{
  const scene=new THREE.Scene();const camera=new THREE.PerspectiveCamera(36,container.clientWidth/container.clientHeight,.1,100);camera.position.set(0,0,9);
  renderer=createRenderer(container);
  scene.add(new THREE.HemisphereLight(0xfff7ef,0x765c4b,2.1));
  const key=new THREE.PointLight(0xffdfc4,32,25);key.position.set(-3,4,6);scene.add(key);
  const rim=new THREE.PointLight(0xe6c9b1,18,18);rim.position.set(4,-2,4);scene.add(rim);
  const orbitGroup=new THREE.Group();scene.add(orbitGroup);
  const orbit=new THREE.Mesh(new THREE.TorusGeometry(2.25,.012,8,180),new THREE.MeshBasicMaterial({color:0xb18b77,transparent:true,opacity:.55}));orbit.rotation.set(1.05,.18,-.35);orbitGroup.add(orbit);
  const orbit2=new THREE.Mesh(new THREE.TorusGeometry(1.65,.009,8,150),new THREE.MeshBasicMaterial({color:0xc9a98f,transparent:true,opacity:.42}));orbit2.rotation.set(.55,-.7,.5);orbitGroup.add(orbit2);
  const main=makeBottle(scene,{x:-.55,y:-.1,scale:.78,tint:0xc9a99a});
  const mini=makeBottle(scene,{x:1.25,y:-.65,scale:.52,tint:0xe0c9b9});
  const pearl=new THREE.Mesh(new THREE.SphereGeometry(.12,24,24),new THREE.MeshPhysicalMaterial({color:0xf2e5d8,metalness:.35,roughness:.12,clearcoat:1}));pearl.position.set(2,.95,.2);scene.add(pearl);
  const resize=()=>{const w=container.clientWidth,h=container.clientHeight;if(!w||!h)return;camera.aspect=w/h;camera.updateProjectionMatrix();renderer.setSize(w,h)};
  const observer=new ResizeObserver(resize);observer.observe(container);
  let frame=0;const animate=()=>{frame=requestAnimationFrame(animate);if(document.hidden)return;const t=performance.now()*.001;main.position.y=-.1+Math.sin(t*.8)*.09;main.rotation.y=Math.sin(t*.35)*.12;mini.position.y=-.65+Math.sin(t*.8+1.2)*.12;mini.rotation.y=-.2+Math.sin(t*.4)*.15;orbitGroup.rotation.z+=.0015;pearl.position.y=.95+Math.sin(t*1.1)*.13;renderer.render(scene,camera)};
  animate();return ()=>{cancelAnimationFrame(frame);observer.disconnect();renderer.dispose();renderer.domElement.remove()};
 }catch(error){if(renderer)renderer.dispose();console.info('3D enhancement unavailable; CSS artwork remains visible.');}
}

if(!reduced){
 const hero=document.querySelector('.hero-art');if(hero)mountHero3D(hero);
 const productStage=document.querySelector('[data-3d-placeholder="true"]');if(productStage)mountHero3D(productStage);
 if(window.gsap){
  window.gsap.from('.hero-copy > *',{y:22,opacity:0,duration:.8,stagger:.12,ease:'power2.out'});
  window.gsap.utils.toArray('.category-card,.product-card').forEach(card=>{
   card.addEventListener('mouseenter',()=>window.gsap.to(card,{y:-5,duration:.25}));
   card.addEventListener('mouseleave',()=>window.gsap.to(card,{y:0,duration:.25}));
  });
 }
}