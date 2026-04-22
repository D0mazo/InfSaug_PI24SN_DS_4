<style>
    .big-status { text-align:center; padding:28px 20px; border-radius:8px; margin-bottom:20px; border:1px solid; }
    .big-status .icon  { font-size:2.2rem; line-height:1; margin-bottom:8px; }
    .big-status .label { font-size:1.1rem; font-weight:600; }
    .big-status .sub   { font-size:.82rem; color:var(--muted); margin-top:4px; }
    .big-valid   { background:var(--green-bg); border-color:#bbf7d0; }
    .big-valid .label { color:var(--green); }
    .big-invalid { background:var(--red-bg); border-color:#fecaca; }
    .big-invalid .label { color:var(--red); }
    .hash-row { display:flex; gap:8px; align-items:flex-start; flex-wrap:wrap; }
    .hash-row .badge { font-family:var(--mono); font-size:.65rem; padding:2px 8px; border-radius:4px; white-space:nowrap; margin-top:4px; font-weight:500; }
    .eq  { background:var(--green-bg); color:var(--green); }
    .neq { background:var(--red-bg);   color:var(--red);   }
</style>

<div class="card">
    <div class="card-header"><span class="dot"></span>Tikrinimo procesas</div>
    <div class="card-body">
        <div class="row">
            <div class="col">
                <div class="info-block ok"><strong>Žingsnis 1</strong><br>Parašas iššifruojamas viešuoju raktu → pradinė maiša.</div>
            </div>
            <div class="col">
                <div class="info-block ok"><strong>Žingsnis 2</strong><br>Nepriklausomai apskaičiuojama gauto pranešimo SHA-256 maiša.</div>
            </div>
            <div class="col">
                <div class="info-block ok"><strong>Žingsnis 3</strong><br>Sutampa → galiojantis. Nesutampa → pranešimas ar parašas pakeistas.</div>
            </div>
        </div>
    </div>
</div>

<?php if ($dataError): ?>
    <div class="card">
        <div class="card-header"><span class="dot"></span>Rezultatas</div>
        <div class="card-body">
            <div class="info-block warn"><?= $dataError ?></div>
            <div class="info-block" style="margin-top:8px;">
                <strong>Proceso žingsniai:</strong><br>
                1. <code>php program3.php</code><br>
                2. <code>php program2.php</code><br>
                3. <a href="program1.php">program1.php</a> → siųskite pranešimą<br>
                4. <a href="program2.php">program2.php</a> → siųskite į čia<br>
                5. Atnaujinkite šį puslapį
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="card">
        <div class="card-header">
            <span class="dot"></span>Socket tikrinimo rezultatas
            <span style="margin-left:auto;font-size:.68rem;color:var(--muted);">
                <?= htmlspecialchars($result['timestamp']) ?>
            </span>
        </div>
        <div class="card-body">

            <div class="big-status <?= $result['valid'] ? 'big-valid' : 'big-invalid' ?>">
                <div class="icon"><?= $result['valid'] ? '✓' : '✗' ?></div>
                <div class="label"><?= $result['valid'] ? 'Parašas galiojantis' : 'Parašas NEGALIOJANTIS' ?></div>
                <div class="sub">
                    <?= $result['valid']
                        ? 'Pranešimas nepakeistas. Parašas atitinka viešąjį raktą.'
                        : 'Maišos nesutampa. Parašas arba pranešimas buvo pakeistas.' ?>
                </div>
            </div>

            <?php if ($result['verify_error']): ?>
                <div class="info-block warn" style="margin-bottom:12px;">
                    <strong>Klaida:</strong> <?= htmlspecialchars($result['verify_error']) ?>
                </div>
            <?php endif; ?>

            <div class="field">
                <label>Pranešimas</label>
                <div class="output-box"><?= htmlspecialchars($result['message']) ?></div>
            </div>

            <div class="field">
                <label>SHA-256 maiša (apskaičiuota iš gauto pranešimo)</label>
                <div class="hash-row">
                    <div class="output-box <?= $result['valid'] ? 'green' : 'red' ?>" style="flex:1;">
                        <?= htmlspecialchars($result['computed_hash']) ?>
                    </div>
                    <span class="badge <?= $result['valid'] ? 'eq' : 'neq' ?>">
                        <?= $result['valid'] ? '= sutampa' : '≠ nesutampa' ?>
                    </span>
                </div>
            </div>

            <div class="field">
                <label>Gautas parašas (Base64)</label>
                <div class="output-box <?= $result['valid'] ? 'green' : 'red' ?>" style="max-height:80px;overflow-y:auto;">
                    <?= htmlspecialchars($result['signature']) ?>
                </div>
            </div>

            <?php if (!$result['valid']): ?>
                <div class="info-block warn">
                    <strong>Priežastis:</strong> Programa 2 pakeitė parašą. Net vienas simbolių pakeitimas
                    visiškai sugadina RSA tikrinimą.
                </div>
            <?php endif; ?>

        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><span class="dot"></span>Rankinis tikrinimas</div>
    <div class="card-body">
        <div class="info-block" style="margin-bottom:14px;">
            Galite patikrinti parašą rankiniu būdu – be socket proceso.
        </div>
        <form method="POST">
            <div class="field">
                <label>Pranešimas</label>
                <textarea name="message" rows="2"
                          placeholder="Pranešimas..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </div>
            <div class="field">
                <label>Parašas (Base64)</label>
                <textarea name="signature" rows="4"
                          placeholder="Base64 parašas..."><?= htmlspecialchars($_POST['signature'] ?? '') ?></textarea>
            </div>
            <div class="field">
                <label>Viešasis raktas (PEM)</label>
                <textarea name="public_key_pem" rows="6"
                          placeholder="-----BEGIN PUBLIC KEY-----&#10;...&#10;-----END PUBLIC KEY-----"><?= htmlspecialchars($_POST['public_key_pem'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-success">&#9654; Tikrinti</button>
        </form>

        <?php if ($manualResult): ?>
            <hr class="divider">
            <div class="big-status <?= $manualResult['valid'] ? 'big-valid' : 'big-invalid' ?>">
                <div class="icon"><?= $manualResult['valid'] ? '✓' : '✗' ?></div>
                <div class="label"><?= $manualResult['valid'] ? 'Parašas galiojantis' : 'Parašas NEGALIOJANTIS' ?></div>
            </div>
            <?php if ($manualResult['error']): ?>
                <div class="info-block warn">
                    <strong>Klaida:</strong> <?= htmlspecialchars($manualResult['error']) ?>
                </div>
            <?php endif; ?>
            <div class="field" style="margin-top:10px;">
                <label>Apskaičiuota SHA-256 maiša</label>
                <div class="output-box <?= $manualResult['valid'] ? 'green' : 'red' ?>">
                    <?= htmlspecialchars($manualResult['computed_hash']) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>