// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines26.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines26 _$Lines26FromJson(Map<String, dynamic> json) => Lines26(
  uuid: json['uuid'] as String,
  kind: json['kind'] as String,
  kindLabel: json['kind_label'] as String,
  name: json['name'] as String,
  extraId: (json['extra_id'] as num?)?.toInt(),
  hallId: (json['hall_id'] as num?)?.toInt(),
  quantity: json['quantity'] as String,
  persons: (json['persons'] as num?)?.toInt(),
  perPerson: json['per_person'] as bool,
  unitPrice: json['unit_price'] as String,
  amount: json['amount'] as String,
  frozen: json['frozen'] as bool,
  note: json['note'] as String?,
);

Map<String, dynamic> _$Lines26ToJson(Lines26 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'kind': instance.kind,
  'kind_label': instance.kindLabel,
  'name': instance.name,
  'extra_id': instance.extraId,
  'hall_id': instance.hallId,
  'quantity': instance.quantity,
  'persons': instance.persons,
  'per_person': instance.perPerson,
  'unit_price': instance.unitPrice,
  'amount': instance.amount,
  'frozen': instance.frozen,
  'note': instance.note,
};
