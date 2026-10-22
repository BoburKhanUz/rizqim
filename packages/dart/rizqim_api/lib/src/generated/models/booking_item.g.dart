// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'booking_item.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BookingItem _$BookingItemFromJson(Map<String, dynamic> json) => BookingItem(
  id: (json['id'] as num).toInt(),
  bookingId: (json['booking_id'] as num).toInt(),
  productVariantId: (json['product_variant_id'] as num).toInt(),
  productName: json['product_name'] as String,
  variantName: json['variant_name'] as String?,
  unit: MenuUnit.fromJson(json['unit'] as String),
  quantity: json['quantity'] as String,
  modifiers: (json['modifiers'] as List<dynamic>?)
      ?.map((e) => e as Map<String, dynamic>)
      .toList(),
  unitPrice: json['unit_price'] as String,
  note: json['note'] as String?,
  dueAt: json['due_at'] == null
      ? null
      : DateTime.parse(json['due_at'] as String),
  createdBy: (json['created_by'] as num?)?.toInt(),
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  booking: json['booking'] == null
      ? null
      : Booking.fromJson(json['booking'] as Map<String, dynamic>),
  variant: json['variant'] == null
      ? null
      : ProductVariant.fromJson(json['variant'] as Map<String, dynamic>),
);

Map<String, dynamic> _$BookingItemToJson(BookingItem instance) =>
    <String, dynamic>{
      'id': instance.id,
      'booking_id': instance.bookingId,
      'product_variant_id': instance.productVariantId,
      'product_name': instance.productName,
      'variant_name': instance.variantName,
      'unit': instance.unit,
      'quantity': instance.quantity,
      'modifiers': instance.modifiers,
      'unit_price': instance.unitPrice,
      'note': instance.note,
      'due_at': instance.dueAt?.toIso8601String(),
      'created_by': instance.createdBy,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'booking': instance.booking,
      'variant': instance.variant,
    };
