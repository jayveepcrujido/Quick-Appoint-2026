<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>LGU Quick Appoint</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

<style>
* { margin:0; padding:0; box-sizing:border-box; }
body, html {
  font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  color:#333;
  min-height:100vh;
  display:flex; flex-direction:column;
  background:url('assets/images/LGU_Unisan.jpg') no-repeat center center fixed;
  background-size:cover;
}
body::before {
  content: "";
  position: fixed;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background: rgba(255, 255, 255, 0.7);
  z-index: 0;
}
header, main, footer {
  position: relative;
  z-index: 1;
}

header {
  display:flex; justify-content:space-between; align-items:center;
  padding:1rem 1.5rem;
  background-image:linear-gradient(to right, #0D92F4, #27548A);
  box-shadow:0 4px 8px rgba(0,0,0,0.2);
  position:relative;
  z-index: 1000;
}
header .logo {
  font-size:1.5rem; font-weight:700;
  color:#fff;
}
nav ul {
  list-style:none; display:flex; gap:2rem;
}
nav ul li a {
  color:white; text-decoration:none; font-weight:600;
  transition:color 0.3s;
}
nav ul li a:hover { color:#a8e0ff; }

/* Hamburger Menu */
.menu-toggle {
  display:none; font-size:1.5rem; color:#fff;
  background:none; border:none; cursor:pointer;
}

/* Mobile Nav */
@media(max-width:768px) {
  nav {
    position:absolute;
    top:100%; right:0; left:0;
    background:#27548A;
    display:none; flex-direction:column;
    padding:1rem;
    z-index: 1000;
  }
  nav.active { display:flex; }
  nav ul { flex-direction:column; gap:1rem; }
  .menu-toggle { display:block; }
}

/* Main */
main {
  flex:1;
  display:flex; flex-direction:column;
  justify-content:center; align-items:center;
  text-align:center; padding:2rem 1rem;
}
main img {
  width:90px; height:90px; border-radius:50%; margin-bottom:1rem;
}
main h1 {
  font-size:2rem; color:#023e8a; margin-bottom:0.5rem;
}
main p {
  font-size:1rem; color:#333; max-width:500px; margin-bottom:1.5rem;
}
main a {
  background:#0077b6; color:#fff;
  padding:0.75rem 1.5rem; border-radius:8px;
  font-weight:bold; text-decoration:none;
  transition:background 0.3s;
}
main a:hover { background:#023e8a; }

/* Footer */
footer {
  background-image:linear-gradient(to right, #0D92F4, #27548A);
  color:white; text-align:center;
  padding:1rem;
  z-index: 1;
}
footer .about, footer .contacts {
  margin-bottom:1rem;
}
footer .contacts ul {
  display:flex; justify-content:center; gap:1.5rem;
  list-style:none; padding:0;
}
footer .contacts ul li a {
  color:white; font-size:1.3rem;
  display:inline-block; transition:color 0.3s;
}
footer .contacts ul li a:hover { color:#a8e0ff; }

@media(max-width:480px) {
  main h1 { font-size:1.6rem; }
  main p { font-size:0.95rem; }
  main a { padding:0.6rem 1.2rem; font-size:0.9rem; }
}

</style>
</head>
<body>
<header>
  <div class="logo">LGU Quick Appoint</div>
  <button class="menu-toggle"><i class="fas fa-bars"></i></button>
  <nav>
    <ul>
      <li><a href="register.php">Register</a></li>
      <li><a href="login.php">Login</a></li>
    </ul>
  </nav>
</header>

<main>
  <img src="assets/images/logo.png" alt="LGU Logo">
  <h1>Welcome to <span style="color:#0077b6;">LGU Quick Appoint</span></h1>
  <p>Book appointments with your local government units quickly and easily. Empowering residents with efficient and transparent public service.</p>
  <a href="register.php">Get Started</a>
</main>

<footer>
  <section class="about">
    <h3>About the system</h3>
    <p>LGU Quick Appoint streamlines appointment scheduling for LGUs, giving residents easy access to services and reducing wait times.</p>
  </section>
  <section class="contacts">
    <ul>
      <li><a href="https://facebook.com" target="_blank"><i class="fab fa-facebook-f"></i></a></li>
      <li><a href="https://instagram.com" target="_blank"><i class="fab fa-instagram"></i></a></li>
      <li><a href="mailto:contact@lguquickappoint.com"><i class="fas fa-envelope"></i></a></li>
    </ul>
  </section>
</footer>

<script>
document.querySelector(".menu-toggle").addEventListener("click",()=>{
  document.querySelector("nav").classList.toggle("active");
});
</script>
</body>
</html>
