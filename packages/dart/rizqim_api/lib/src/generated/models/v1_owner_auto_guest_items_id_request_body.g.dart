// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_auto_guest_items_id_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerAutoGuestItemsIdRequestBody _$V1OwnerAutoGuestItemsIdRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerAutoGuestItemsIdRequestBody(
  quantityPerGuest: json['quantity_per_guest'] as num?,
  isActive: json['is_active'] as bool?,
  sortOrder: (json['sort_order'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1OwnerAutoGuestItemsIdRequestBodyToJson(
  V1OwnerAutoGuestItemsIdRequestBody instance,
) => <String, dynamic>{
  'quantity_per_guest': instance.quantityPerGuest,
  'is_active': instance.isActive,
  'sort_order': instance.sortOrder,
};
