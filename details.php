<!doctype html>
<html lang="fr">
  <? 
  $id = $_GET['id'] ?? null;
  $type = $_GET['type'] ?? 'event';
  
  // Simulation de données (Normalement via SQL SELECT * FROM ... WHERE id = $id)
  $items = [
      'event' => [
          '1' => ['title' => 'Atelier Upcycling Bois', 'category' => 'Artisanat', 'organizer' => 'Jean Menuisier', 'date' => '15/03/2024', 'location' => 'Atelier 4, Paris', 'description' => 'Apprendre à fabriquer des petits meubles avec des palettes récupérées.', 'status' => 'En attente', 'image' => 'https://picsum.photos/seed/event1/800/400'],
          '2' => ['title' => 'Conférence Zéro Déchet', 'category' => 'Éducation', 'organizer' => 'Association GreenLife', 'date' => '22/03/2024', 'location' => 'Salle Polyvalente, Lyon', 'description' => 'Discussion sur les enjeux du recyclage et les gestes du quotidien.', 'status' => 'En attente', 'image' => 'https://picsum.photos/seed/event2/800/400']
      ],
      'catalog' => [
          '1' => ['title' => 'Kit Compostage', 'category' => 'Matériel', 'price' => '25€', 'description' => 'Kit complet pour débuter.', 'status' => 'Disponible', 'image' => 'https://picsum.photos/seed/catalog1/800/400']
      ],
      'user' => [
          '1' => ['title' => 'Marie Dupont', 'category' => 'Particulier', 'email' => 'marie@mail.com', 'status' => 'Actif', 'description' => 'Utilisatrice active depuis Janvier 2024. Participe régulièrement aux ateliers bois.', 'image' => 'https://picsum.photos/seed/user1/300/300'],
          '2' => ['title' => 'Marc Durand', 'category' => 'Professionnel', 'email' => 'marc.pro@gmail.com', 'status' => 'Actif', 'description' => 'Menuisier professionnel proposant des formations sur la plateforme.', 'image' => 'https://picsum.photos/seed/user2/300/300']
      ]
  ];

  $item = $items[$type][$id] ?? null;
  $title = ($item ? $item['title'] : 'Détails') . ' - UpcycleConnect'; 
  include 'include/head.php'; 
  ?>
  <body>
    <? include 'include/header.php'; ?>

    <main class="admin-layout">
      <? include 'include/sidebar.php'; ?>

      <section class="admin-content">
        <section class="admin-section">
          <a href="javascript:history.back()" class="btn-outline" style="margin-bottom: 20px; display: inline-flex; align-items: center; gap: 8px;">
            ← Retour
          </a>

          <? if ($item): ?>            
            <h1>Détails : <? echo $item['title']; ?></h1>
            <div class="admin-card">
              <img src="<? echo $item['image']; ?>" alt="<? echo $item['title']; ?>" class="<? echo $type === 'user' ? 'user-profile-image' : 'detail-main-image'; ?>">

              <div class="grid-2">
                <div class="form-group">
                  <label>Catégorie</label>
                  <p><strong><? echo $item['category']; ?></strong></p>
                </div>
                <div class="form-group">
                  <label>Statut actuel</label>
                  <p><? echo $item['status']; ?></p>
                </div>

                <? if ($type === 'event'): ?>
                  <div class="form-group">
                    <label>Organisateur</label>
                    <p><? echo $item['organizer']; ?></p>
                  </div>
                  <div class="form-group">
                    <label>Date & Lieu</label>
                    <p><? echo $item['date']; ?> - <? echo $item['location']; ?></p>
                  </div>
                <? elseif ($type === 'catalog'): ?>
                  <div class="form-group">
                    <label>Prix unitaire</label>
                    <p><? echo $item['price']; ?></p>
                  </div>
                <? elseif ($type === 'user'): ?>
                  <div class="form-group">
                    <label>Email</label>
                    <p><? echo $item['email']; ?></p>
                  </div>
                <? endif; ?>
              </div>

              <div class="form-group" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
                <label>Description complète</label>
                <p style="line-height: 1.6;"><? echo $item['description']; ?></p>
              </div>

              <div class="row" style="margin-top: 30px;">
                <button type="button" class="btn-primary" onclick="openEditModal(this)" 
                        data-id="<? echo $id; ?>" data-type="<? echo $type; ?>"
                        data-title="<? echo htmlspecialchars($item['title']); ?>"
                        data-category="<? echo htmlspecialchars($item['category']); ?>"
                        data-status="<? echo htmlspecialchars($item['status']); ?>"
                        data-description="<? echo htmlspecialchars($item['description']); ?>"
                        <? if ($type === 'event'): ?>
                          data-organizer="<? echo htmlspecialchars($item['organizer']); ?>"
                          data-date="<? echo htmlspecialchars($item['date']); ?>"
                          data-location="<? echo htmlspecialchars($item['location']); ?>"
                        <? elseif ($type === 'catalog'): ?>
                          data-price="<? echo htmlspecialchars($item['price']); ?>"
                        <? elseif ($type === 'user'): ?>
                          data-email="<? echo htmlspecialchars($item['email']); ?>"
                        <? endif; ?>
                >Modifier les informations</button>
                <? if ($type === 'event' && $item['status'] === 'En attente'): ?>
                  <button class="btn-outline">Valider l'événement</button>
                <? endif; ?>
              </div>
            </div>
          <? else: ?>
            <p>Élément introuvable.</p>
          <? endif; ?>
        </section>
      </section>

      <!-- Edit Modal -->
      <div id="editModalOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 hidden opacity-0 transition-opacity duration-300">
        <div id="editModalContent" class="bg-white rounded-lg shadow-xl w-full max-w-lg overflow-hidden transform -translate-y-full transition-transform duration-300 ease-out">
          <div class="p-6">
            <div class="flex justify-between items-center pb-4 border-b border-gray-100 mb-4">
              <h5 class="text-xl font-bold text-brand-dark" id="modal-display-title">Modifier l'élément</h5>
              <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            
            <form action="#" method="POST" class="flex flex-col m-0">
              <div class="space-y-6"> <!-- Using space-y for vertical spacing -->
                <input type="hidden" id="modal-item-id" name="id">
                <input type="hidden" id="modal-item-type" name="type">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <label for="modal-item-title" class="block text-sm font-bold text-gray-700 uppercase tracking-wider">Nom / Titre</label>
                    <input type="text" id="modal-item-title" name="title" class="w-full p-3.5 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:border-brand-green focus:ring-4 focus:ring-brand-green/10 transition-all duration-200 outline-none">
                  </div>
                  <div class="space-y-2">
                    <label for="modal-item-status" class="block text-sm font-bold text-gray-700 uppercase tracking-wider">Statut</label>
                    <select id="modal-item-status" name="status" class="w-full p-3.5 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:border-brand-green focus:ring-4 focus:ring-brand-green/10 transition-all duration-200 outline-none appearance-none">
                      <option value="Actif">Actif</option>
                      <option value="Disponible">Disponible</option>
                      <option value="En attente">En attente</option>
                      <option value="Rupture">Rupture</option>
                      <option value="Désactivé">Désactivé</option>
                    </select>
                  </div>
                  <div class="space-y-2" id="modal-item-email-group" style="display: none;">
                    <label for="modal-item-email" class="block text-sm font-bold text-gray-700 uppercase tracking-wider">Email de contact</label>
                    <input type="email" id="modal-item-email" name="email" class="w-full p-3.5 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:border-brand-green focus:ring-4 focus:ring-brand-green/10 transition-all duration-200 outline-none">
                  </div>
                  <div class="space-y-2" id="modal-item-price-group" style="display: none;">
                    <label for="modal-item-price" class="block text-sm font-bold text-gray-700 uppercase tracking-wider">Prix unitaire (€)</label>
                    <input type="text" id="modal-item-price" name="price" class="w-full p-3.5 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:border-brand-green focus:ring-4 focus:ring-brand-green/10 transition-all duration-200 outline-none">
                  </div>
                  <!-- Ajoutez d'autres champs spécifiques aux types ici si nécessaire -->
                </div>
                <div class="space-y-2">
                  <label for="modal-item-description" class="block text-sm font-bold text-gray-700 uppercase tracking-wider">Description complète</label>
                  <textarea id="modal-item-description" name="description" class="w-full p-3.5 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:border-brand-green focus:ring-4 focus:ring-brand-green/10 transition-all duration-200 outline-none resize-y min-h-[140px]"></textarea>
                </div>
              </div>
              <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Annuler</button>
                <button type="submit" class="px-4 py-2 text-white bg-emerald-600 rounded-md hover:bg-emerald-700">Enregistrer les modifications</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>
    <? include 'include/footer.php'; ?>

    <script>
        const overlay = document.getElementById('editModalOverlay');
        const content = document.getElementById('editModalContent');
        const modalDisplayTitle = document.getElementById('modal-display-title');

        function openEditModal(btn) {
            // Récupération des données
            const id = btn.getAttribute('data-id');
            const type = btn.getAttribute('data-type');
            const title = btn.getAttribute('data-title');
            const status = btn.getAttribute('data-status');
            const description = btn.getAttribute('data-description');
            const email = btn.getAttribute('data-email');
            const price = btn.getAttribute('data-price');

            // Remplissage du formulaire
            modalDisplayTitle.textContent = `Modifier ${title}`;
            document.getElementById('modal-item-id').value = id;
            document.getElementById('modal-item-type').value = type;
            document.getElementById('modal-item-title').value = title;
            document.getElementById('modal-item-description').value = description;
            document.getElementById('modal-item-status').value = status;

            // Champs spécifiques
            const emailGroup = document.getElementById('modal-item-email-group');
            const priceGroup = document.getElementById('modal-item-price-group');
            
            emailGroup.style.display = (type === 'user') ? 'block' : 'none';
            if(type === 'user') document.getElementById('modal-item-email').value = email;

            priceGroup.style.display = (type === 'catalog') ? 'block' : 'none';
            if(type === 'catalog') document.getElementById('modal-item-price').value = price;

            // Animation d'ouverture
            overlay.classList.remove('hidden');
            setTimeout(() => {
                overlay.classList.add('opacity-100');
                content.classList.remove('-translate-y-full');
                content.classList.add('translate-y-0');
            }, 10);
        }

        function closeEditModal() {
            // Animation de fermeture
            overlay.classList.remove('opacity-100');
            content.classList.remove('translate-y-0');
            content.classList.add('-translate-y-full');

            setTimeout(() => {
                overlay.classList.add('hidden');
            }, 300);
        }

        // Fermer si on clique sur l'overlay
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeEditModal();
        });
    </script>
  </body>
</html>