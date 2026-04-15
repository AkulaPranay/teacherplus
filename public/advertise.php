<?php
$page_title = "Advertise - TeacherPlus";
include '../includes/header.php';
?>

<style>
body {
    background-color: #f2f2f2;
    font-family: Arial, sans-serif;
}

.advertise-container {
    max-width: 1000px;
       margin: 50px auto;
    
    padding: 50px 80px;

    font-size: 14px;
    line-height: 1.7;
   color: #121212;
}

/* Heading */
.advertise-container h2 {
    font-size: 18px;
    font-weight: bold;
     color: #121212;
}

/* Red text */
.red-text {
    color: #ff3b00;
    font-weight: bold;
}

/* Blue highlight text */
.blue-text {
    color: #0066ff;
    font-weight: bold;
}

/* Section title */
.section-title {
    color: #ff3b00;
    font-weight: bold;
    margin-top: 20px;
}

/* Bullet list */
.advertise-container ul {
    margin-top: 10px;
    padding-left: 20px;
}

.advertise-container ul li {
    margin-bottom: 10px;
}

/* Bottom center text */
.center-text {
    text-align: center;
    margin-top: 30px;
    font-weight: bold;
    color: #0066ff;
}

/* Contact link */
.contact {
    text-align: center;
    margin-top: 10px;
}

.contact a {
    color: #ff6b3d;
    text-decoration: none;
    font-weight: bold;
}

.contact a:hover {
    text-decoration: underline;
}
</style>

<div class="advertise-container">

    <h2>
        Welcome to Teacher Plus – <span style="font-weight: normal;">A Great Place for Brands in Education.</span>
    </h2>

    <p>
        <span class="red-text">Teacher Plus</span> is a monthly magazine started in 1989 for school teachers 
        and teacher educators to learn and share their ideas and experiences. It endeavours, through its articles, 
        to spark ideas and means of their implementation in schools and learning spaces, and also to give teachers 
        a sense of themselves – as people, as professionals, as important catalysts of human development.
    </p>

    <p class="blue-text">
        Over 25 years, we have built a community of teachers, educators and parents – 
        the right place for you to share information about your brand.
    </p>

    <p class="section-title">Why advertise in Teacher Plus?</p>

    <ul>
        <li>The only magazine in India <span class="blue-text">aimed at teachers and schools.</span></li>

        <li>A <span class="blue-text">readership</span> of more than 35000 throughout the country and the number is growing.</li>

        <li>The long shelf life of our magazine ensures greater exposure for your brand.</li>

        <li>Our magazines are mailed directly to the <span class="blue-text">niche target</span> market you want to reach reducing your cost per contact.</li>

        <li><span class="blue-text">Greater visibility</span> for your brand through our magazine, digital issue, and website.</li>

        <li>Our varied content caters to the needs of ICSE, CBSE, IB, Missionary, Government, and chains of schools providing a 
        <span class="blue-text">wider market reach.</span></li>
    </ul>

    <p class="center-text">
        Do you want to share information about your company, a new product, or an upcoming event?
    </p>

    <p class="contact">
        To contact us, please <a href="contact.php">Click Here</a>
    </p>

</div>

<?php include '../includes/footer.php'; ?>
