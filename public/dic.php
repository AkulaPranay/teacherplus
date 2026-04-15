<?php
$page_title = "Disclaimer";
 include '../includes/header.php'; 
?>

<div class="content">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>

    <p>
        To the maximum extent permitted by applicable law, we exclude all representations, warranties and conditions
        relating to our website and the use of this website. Nothing in this disclaimer will:
    </p>

    <ul>
        <li>limit or exclude our or your liability for death or personal injury;</li>
        <li>limit or exclude our or your liability for fraud or fraudulent misrepresentation;</li>
        <li>limit any of our or your liabilities in any way that is not permitted under applicable law; or</li>
        <li>exclude any of our or your liabilities that may not be excluded under applicable law.</li>
    </ul>

    <p>
        The limitations and prohibitions of liability set in this Section and elsewhere in this disclaimer:
        (a) are subject to the preceding paragraph; and (b) govern all liabilities arising under the disclaimer,
        including liabilities arising in contract, in tort and for breach of statutory duty.
    </p>

    <p>
        As long as the website and the information and services on the website are provided free of charge,
        we will not be liable for any loss or damage of any nature.
    </p>
</div>

<style>
    .content {
        max-width: 1200px;
        margin: 40px auto;
        padding: 0 40px 60px;
    }

    .content h1 {
        color: #e87722;
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 20px;
    }

    .content p {
        line-height: 1.7;
        margin-bottom: 16px;
        color: #333;
    }

    .content ul {
        list-style: none;
        margin: 16px 0 20px 0;
        padding: 0;
    }

    .content ul li {
        position: relative;
        padding-left: 20px;
        margin-bottom: 10px;
        line-height: 1.7;
        color: #333;
    }

    .content ul li::before {
        content: "▪";
        position: absolute;
        left: 0;
        color: #333;
        font-size: 12px;
        top: 3px;
    }
</style>

<?php include '../includes/footer.php'; ?>