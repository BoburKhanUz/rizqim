// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'menu_batch.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

MenuBatch _$MenuBatchFromJson(Map<String, dynamic> json) => MenuBatch(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  storeId: (json['store_id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  productId: (json['product_id'] as num).toInt(),
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  label: json['label'] as String?,
  quantityProduced: json['quantity_produced'] as String,
  quantityLeft: json['quantity_left'] as String,
  startedAt: DateTime.parse(json['started_at'] as String),
  soldOutAt: json['sold_out_at'] == null
      ? null
      : DateTime.parse(json['sold_out_at'] as String),
  closedAt: json['closed_at'] == null
      ? null
      : DateTime.parse(json['closed_at'] as String),
  openedBy: (json['opened_by'] as num?)?.toInt(),
  closedBy: (json['closed_by'] as num?)?.toInt(),
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  oversoldQuantity: json['oversold_quantity'] as String,
  writtenOffQuantity: json['written_off_quantity'] as String,
  isOpen: json['is_open'] as bool,
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch.fromJson(json['branch'] as Map<String, dynamic>),
  product: json['product'] == null
      ? null
      : Product.fromJson(json['product'] as Map<String, dynamic>),
  variant: json['variant'] == null
      ? null
      : ProductVariant.fromJson(json['variant'] as Map<String, dynamic>),
);

Map<String, dynamic> _$MenuBatchToJson(MenuBatch instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'store_id': instance.storeId,
  'branch_id': instance.branchId,
  'product_id': instance.productId,
  'product_variant_id': instance.productVariantId,
  'label': instance.label,
  'quantity_produced': instance.quantityProduced,
  'quantity_left': instance.quantityLeft,
  'started_at': instance.startedAt.toIso8601String(),
  'sold_out_at': instance.soldOutAt?.toIso8601String(),
  'closed_at': instance.closedAt?.toIso8601String(),
  'opened_by': instance.openedBy,
  'closed_by': instance.closedBy,
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
  'oversold_quantity': instance.oversoldQuantity,
  'written_off_quantity': instance.writtenOffQuantity,
  'is_open': instance.isOpen,
  'store': instance.store,
  'branch': instance.branch,
  'product': instance.product,
  'variant': instance.variant,
};
