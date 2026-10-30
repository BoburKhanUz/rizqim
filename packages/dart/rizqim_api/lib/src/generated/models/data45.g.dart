// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data45.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data45 _$Data45FromJson(Map<String, dynamic> json) => Data45(
  id: (json['id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  name: json['name'] as String,
  basis: json['basis'] as String,
  basisLabel: json['basis_label'] as String,
  categoryId: (json['category_id'] as num?)?.toInt(),
  productId: (json['product_id'] as num?)?.toInt(),
  quantity: (json['quantity'] as num).toInt(),
  unitPrice: json['unit_price'] as String,
  mxik: json['mxik'] as String?,
  packageCode: json['package_code'] as String?,
  unit: json['unit'] as String,
  orderTypes: (json['order_types'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  isActive: json['is_active'] as bool,
  sortOrder: (json['sort_order'] as num).toInt(),
);

Map<String, dynamic> _$Data45ToJson(Data45 instance) => <String, dynamic>{
  'id': instance.id,
  'branch_id': instance.branchId,
  'name': instance.name,
  'basis': instance.basis,
  'basis_label': instance.basisLabel,
  'category_id': instance.categoryId,
  'product_id': instance.productId,
  'quantity': instance.quantity,
  'unit_price': instance.unitPrice,
  'mxik': instance.mxik,
  'package_code': instance.packageCode,
  'unit': instance.unit,
  'order_types': instance.orderTypes,
  'is_active': instance.isActive,
  'sort_order': instance.sortOrder,
};
