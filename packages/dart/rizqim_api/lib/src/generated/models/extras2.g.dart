// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'extras2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Extras2 _$Extras2FromJson(Map<String, dynamic> json) => Extras2(
  extraId: (json['extra_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  unitPrice: json['unit_price'] as num?,
  perPerson: json['per_person'] as bool?,
  quantity: json['quantity'] as num?,
  note: json['note'] as String?,
);

Map<String, dynamic> _$Extras2ToJson(Extras2 instance) => <String, dynamic>{
  'extra_id': instance.extraId,
  'name': instance.name,
  'unit_price': instance.unitPrice,
  'per_person': instance.perPerson,
  'quantity': instance.quantity,
  'note': instance.note,
};
