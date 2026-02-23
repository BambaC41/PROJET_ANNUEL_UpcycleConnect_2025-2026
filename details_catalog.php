<!doctype html>
<html lang="fr">
  <? 
  $id = $_GET['id'] ?? null;
  
  $items = [
      '1' => ['title' => 'Kit Compostage', 'category' => 'Matériel', 'price' => '25€', 'description' => 'Kit complet pour débuter.', 'status' => 'Disponible', 'image' => 'https://picsum.photos/seed/catalog1/800/400']
  ];

  $item = $items[$id] ?? null;
  $title = ($item ? $item['title'] : 'Détails Produit') . ' - UpcycleConnect'; 
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
            <h1>Détails Produit : <? echo $item['title']; ?></h1>
            <div class="admin-card">
              <img src="<? echo $item['image']; ?>" alt="<? echo $item['title']; ?>" class="detail-main-image">

              <div class="grid-2">
                <div class="form-group">
                  <label>Catégorie</label>
                  <p><strong><? echo $item['category']; ?></strong></p>
                </div>
                <div class="form-group">
                  <label>Statut</label>
                  <p><? echo $item['status']; ?></p>
                </div>
                <div class="form-group">
                  <label>Prix unitaire</label>
                  <p><? echo $item['price']; ?></p>
                </div>
              </div>

              <div class="form-group" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
                <label>Description</label>
                <p style="line-height: 1.6;"><? echo $item['description']; ?></p>
              </div>

              <div class="row" style="margin-top: 30px;">
                <button type="button" class="btn-primary" onclick="openEditModal(this)" 
                        data-id="<? echo $id; ?>" 
                        data-title="<? echo htmlspecialchars($item['title']); ?>"
                        data-price="<? echo htmlspecialchars($item['price']); ?>"
                        data-status="<? echo htmlspecialchars($item['status']); ?>"
                        data-description="<? echo htmlspecialchars($item['description']); ?>"
                >Modifier l'article</button>
              </div>
            </div>
          <? else: ?>
            <p>Produit introuvable.</p>
          <? endif; ?>
        </section>
      </section>

      <!-- Edit Modal -->
      <div id="editModalOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 hidden opacity-0 transition-opacity duration-300">
        <div id="editModalContent" class="bg-white rounded-lg shadow-xl w-full max-w-lg overflow-hidden transform -translate-y-full transition-transform duration-300 ease-out">
          <div class="p-6">
            <div class="flex justify-between items-center pb-4 border-b border-gray-100 mb-4">
              <h5 class="text-xl font-bold text-brand-dark" id="modal-display-title">Modifier l'article</h5>
              <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            
            <form action="#" method="POST" class="flex flex-col m-0">
              <div class="space-y-6">
                <input type="hidden" id="modal-item-id" name="id">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <label for="modal-item-title" class="block text-sm font-bold text-gray-700 uppercase tracking-wider">Nom du produit</label>
                    <input type="text" id="modal-item-title" name="title" class="w-full p-3.5 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:border-brand-green focus:ring-4 focus:ring-brand-green/10 transition-all duration-200 outline-none">
                  </div>
                  <div class="space-y-2">
                    <label for="modal-item-price" class="block text-sm font-bold text-gray-700 uppercase tracking-wider">Prix (€)</label>
                    <input type="text" id="modal-item-price" name="price" class="w-full p-3.5 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:border-brand-green focus:ring-4 focus:ring-brand-green/10 transition-all duration-200 outline-none">
                  </div>
                </div>
                <div class="space-y-2">
                  <label for="modal-item-status" class="block text-sm font-bold text-gray-700 uppercase tracking-wider">Statut</label>
                  <select id="modal-item-status" name="status" class="w-full p-3.5 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:border-brand-green focus:ring-4 focus:ring-brand-green/10 transition-all duration-200 outline-none appearance-none">
                    <option value="Disponible">Disponible</option>
                    <option value="Rupture">Rupture</option>
                  </select>
                </div>
                <div class="space-y-2">
                  <label for="modal-item-description" class="block text-sm font-bold text-gray-700 uppercase tracking-wider">Description</label>
                  <textarea id="modal-item-description" name="description" class="w-full p-3.5 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:border-brand-green focus:ring-4 focus:ring-brand-green/10 transition-all duration-200 outline-none resize-y min-h-[140px]"></textarea>
                </div>
              </div>
              <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Annuler</button>
                <button type="submit" class="px-4 py-2 text-white bg-emerald-600 rounded-md hover:bg-emerald-700">Enregistrer</button>
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

        function openEditModal(btn) {
            document.getElementById('modal-item-id').value = btn.getAttribute('data-id');
            document.getElementById('modal-item-title').value = btn.getAttribute('data-title');
            document.getElementById('modal-item-price').value = btn.getAttribute('data-price');
            document.getElementById('modal-item-description').value = btn.getAttribute('data-description');
            document.getElementById('modal-item-status').value = btn.getAttribute('data-status');

            overlay.classList.remove('hidden');
            setTimeout(() => {
                overlay.classList.add('opacity-100');
                content.classList.remove('-translate-y-full');
                content.classList.add('translate-y-0');
            }, 10);
        }

        function closeEditModal() {
            overlay.classList.remove('opacity-100');
            content.classList.remove('translate-y-0');
            content.classList.add('-translate-y-full');
            setTimeout(() => overlay.classList.add('hidden'), 300);
        }

        overlay.addEventListener('click', (e) => { if (e.target === overlay) closeEditModal(); });
    </script>
  </body>
</html>