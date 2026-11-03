// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'parts.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Parts _$PartsFromJson(Map<String, dynamic> json) => Parts(
  slotName: json['slot_name'] as String,
  name: json['name'] as String,
  variantName: json['variant_name'] as String?,
  quantity: (json['quantity'] as num).toInt(),
  label: json['label'] as String,
);

Map<String, dynamic> _$PartsToJson(Parts instance) => <String, dynamic>{
  'slot_name': instance.slotName,
  'name': instance.name,
  'variant_name': instance.variantName,
  'quantity': instance.quantity,
  'label': instance.label,
};
