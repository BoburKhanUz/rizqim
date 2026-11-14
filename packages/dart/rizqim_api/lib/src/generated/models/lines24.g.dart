// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines24.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines24 _$Lines24FromJson(Map<String, dynamic> json) => Lines24(
  uuid: json['uuid'] as String,
  kind: json['kind'] as String,
  name: json['name'] as String,
  unitPrice: json['unit_price'] as String,
  quantity: json['quantity'] as String,
  persons: (json['persons'] as num?)?.toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Lines24ToJson(Lines24 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'kind': instance.kind,
  'name': instance.name,
  'unit_price': instance.unitPrice,
  'quantity': instance.quantity,
  'persons': instance.persons,
  'amount': instance.amount,
};
