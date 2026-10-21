// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_auto_guest_items_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerAutoGuestItemsRequestBody _$V1OwnerAutoGuestItemsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerAutoGuestItemsRequestBody(
  branchId: (json['branch_id'] as num).toInt(),
  productVariantId: (json['product_variant_id'] as num).toInt(),
  quantityPerGuest: json['quantity_per_guest'] as num,
  hallId: (json['hall_id'] as num?)?.toInt(),
  isActive: json['is_active'] as bool?,
  sortOrder: (json['sort_order'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1OwnerAutoGuestItemsRequestBodyToJson(
  V1OwnerAutoGuestItemsRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'hall_id': instance.hallId,
  'product_variant_id': instance.productVariantId,
  'quantity_per_guest': instance.quantityPerGuest,
  'is_active': instance.isActive,
  'sort_order': instance.sortOrder,
};
