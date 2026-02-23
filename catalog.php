<!doctype html>
<html lang="fr">
  <? 
  $title = 'Gestion du Catalogue - UpcycleConnect'; 
  include 'include/head.php'; 
  ?>
  <body>

    <? include 'include/header.php'; ?>

    <main class="admin-layout">
      <? include 'include/sidebar.php'; ?>

      <section class="admin-content">
        <section class="admin-section">
          <div class="panel-head">
            <div>
              <h1>Catalogue des offres</h1>
              <p class="muted">Gérez les produits, kits et prestations de services disponibles sur la plateforme.</p>
            </div>
            <div class="dash-actions">
              <button class="btn-primary">Ajouter un article</button>
              <button class="btn-outline">Gérer les catégories</button>
            </div>
          </div>

          <div class="table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Produit / Service</th>
                  <th>Catégorie</th>
                  <th>Prix</th>
                  <th>Statut</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>
                    <a href="details_catalog.php?id=1" class="detail-link">
                      <strong>Kit Compostage Appartement</strong>
                    </a>
                  </td>
                  <td>Matériel</td>
                  <td>25,00€</td>
                  <td><span class="pill pill-green">Disponible</span></td>
                  <td>
                    <button class="btn-outline">Modifier</button>
                  </td>
                </tr>
                <tr>
                  <td>
                    <a href="details_catalog.php?id=2" class="detail-link">
                      <strong>Formation Réparation Vélo</strong>
                    </a>
                  </td>
                  <td>Service</td>
                  <td>45,00€</td>
                  <td><span class="pill pill-green">Disponible</span></td>
                  <td>
                    <button class="btn-outline">Modifier</button>
                  </td>
                </tr>
                <tr>
                  <td>
                    <a href="details_catalog.php?id=3" class="detail-link">
                      <strong>Bac de tri sélectif (Lot de 3)</strong>
                    </a>
                  </td>
                  <td>Matériel</td>
                  <td>15,00€</td>
                  <td><span class="pill pill-gray">Rupture</span></td>
                  <td>
                    <button class="btn-outline">Modifier</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </section>
    </main>

    <? include 'include/footer.php'; ?>
  </body>
</html>