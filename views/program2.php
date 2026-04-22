<div class="card">
    <div class="card-header"><span class="dot"></span>Man-in-the-Middle tarpininkės vaidmuo</div>
    <div class="card-body">
        <div class="row">
            <div class="col">
                <div class="info-block warn">
                    <strong>Kas vyksta čia?</strong><br>
                    Tarpininkė gauna pranešimą, parašą ir viešąjį raktą iš Programos 1,
                    gali pakeisti parašą ir persiunčia duomenis į Programą 3.
                </div>
            </div>
            <div class="col">
                <div class="info-block warn">
                    <strong>Kodėl keitimas aptinkamas?</strong><br>
                    Parašas yra SHA-256 maišos RSA šifras. Bet koks pakeitimas
                    sukels maišų neatitikimą Programoje 3 → parašas negaliojantis.
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($dataError): ?>
    <div class="card">
        <div class="card-header"><span class="dot"></span>Statusas</div>
        <div class="card-body">
            <div class="info-block warn"><?= $dataError ?></div>
            <div class="info-block" style="margin-top:8px;">
                <strong>Žingsniai:</strong><br>
                1. <code>php program3.php</code> – paleiskite tikrinimo serverį<br>
                2. <code>php program2.php</code> – paleiskite šį serverį<br>
                3. <a href="program1.php">program1.php</a> – nusiųskite pranešimą<br>
                4. Atnaujinkite šį puslapį
            </div>
        </div>
    </div>

<?php else: ?>

    <div class="card">
        <div class="card-header"><span class="dot"></span>Gauti duomenys iš Programos 1</div>
        <div class="card-body">
            <div class="field">
                <label>Pranešimas</label>
                <div class="output-box"><?= htmlspecialchars($data['message']) ?></div>
            </div>
            <div class="field">
                <label>Originalus parašas (Base64)</label>
                <div class="output-box red" style="max-height:80px;overflow-y:auto;">
                    <?= htmlspecialchars($data['signature']) ?>
                </div>
            </div>
            <div class="field">
                <label>Viešasis raktas (PEM)</label>
                <div class="output-box" style="max-height:100px;overflow-y:auto;">
                    <?= htmlspecialchars($data['public_key_pem']) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="dot"></span>Parašo keitimas ir siuntimas į Programą 3</div>
        <div class="card-body">
            <form method="POST">
                <div class="field">
                    <label for="sigField">Parašas (galite keisti)</label>
                    <textarea id="sigField" name="signature" rows="5"
                              oninput="checkChange()"><?= htmlspecialchars($data['signature']) ?></textarea>
                    <div class="tamper-hint" id="tampHint" style="display:none;">
                        ⚠ Parašas pakeistas – tikrinimas greičiausiai nepavyks
                    </div>
                </div>

                <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
                    <button type="button" class="btn btn-danger" onclick="tamperSignature()">✎ Sugadinti automatiškai</button>
                    <button type="button" class="btn btn-ghost" onclick="resetSignature()">↺ Atstatyti originalą</button>
                </div>

                <div class="step-arrow">↓ Persiunčiama į Programą 3 per TCP socket (port <?= PROG3_PORT ?>) ↓</div>
                <button type="submit" class="btn btn-danger">&#9654; Siųsti į Programą 3</button>
            </form>

            <?php if ($sendResult): ?>
                <hr class="divider">

                <?php if ($sendResult['changed']): ?>
                    <div class="status status-invalid">⚠ Parašas buvo pakeistas prieš siunčiant</div>
                <?php else: ?>
                    <div class="status status-valid">✓ Parašas nebuvo keičiamas</div>
                <?php endif; ?>

                <div class="field" style="margin-top:14px;">
                    <label>Išsiųstas JSON į Programą 3</label>
                    <div class="json-preview"><?= htmlspecialchars($sendResult['payload_json']) ?></div>
                </div>

                <div class="field">
                    <label>Atsakymas iš Programos 3</label>
                    <div class="output-box <?= str_starts_with($sendResult['response'], 'KLAIDA') ? 'red' : 'green' ?>">
                        <?= htmlspecialchars($sendResult['response']) ?>
                    </div>
                </div>

                <?php if (str_starts_with($sendResult['response'], 'KLAIDA')): ?>
                    <div class="info-block warn" style="margin-top:8px;">
                        <strong>Programa 3 neveikia.</strong> Terminale: <code>php program3.php</code>
                    </div>
                <?php else: ?>
                    <div class="info-block ok" style="margin-top:8px;">
                        Duomenys persiųsti. Eikite į <a href="program3.php">program3.php</a> matyti rezultatą.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>