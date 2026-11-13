// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'combo_parts8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ComboParts8 _$ComboParts8FromJson(Map<String, dynamic> json) => ComboParts8(
  slotName: json['slot_name'] as String,
  name: json['name'] as String,
  variantName: json['variant_name'] as String?,
  quantity: (json['quantity'] as num).toInt(),
  label: json['label'] as String,
);

Map<String, dynamic> _$ComboParts8ToJson(ComboParts8 instance) =>
    <String, dynamic>{
      'slot_name': instance.slotName,
      'name': instance.name,
      'variant_name': instance.variantName,
      'quantity': instance.quantity,
      'label': instance.label,
    };
