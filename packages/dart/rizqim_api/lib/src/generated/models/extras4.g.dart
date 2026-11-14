// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'extras4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Extras4 _$Extras4FromJson(Map<String, dynamic> json) => Extras4(
  lineUuid: json['line_uuid'] as String?,
  extraId: (json['extra_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  unitPrice: json['unit_price'] as num?,
  perPerson: json['per_person'] as bool?,
  quantity: json['quantity'] as num?,
  note: json['note'] as String?,
);

Map<String, dynamic> _$Extras4ToJson(Extras4 instance) => <String, dynamic>{
  'line_uuid': instance.lineUuid,
  'extra_id': instance.extraId,
  'name': instance.name,
  'unit_price': instance.unitPrice,
  'per_person': instance.perPerson,
  'quantity': instance.quantity,
  'note': instance.note,
};
