// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines20.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines20 _$Lines20FromJson(Map<String, dynamic> json) => Lines20(
  uuid: json['uuid'] as String,
  kind: json['kind'] as String,
  name: json['name'] as String,
  unitPrice: json['unit_price'] as String,
  quantity: json['quantity'] as String,
  persons: (json['persons'] as num?)?.toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Lines20ToJson(Lines20 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'kind': instance.kind,
  'name': instance.name,
  'unit_price': instance.unitPrice,
  'quantity': instance.quantity,
  'persons': instance.persons,
  'amount': instance.amount,
};
