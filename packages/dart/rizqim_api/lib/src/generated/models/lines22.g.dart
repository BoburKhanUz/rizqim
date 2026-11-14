// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines22.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines22 _$Lines22FromJson(Map<String, dynamic> json) => Lines22(
  uuid: json['uuid'] as String,
  kind: json['kind'] as String,
  name: json['name'] as String,
  unitPrice: json['unit_price'] as String,
  quantity: json['quantity'] as String,
  persons: (json['persons'] as num?)?.toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Lines22ToJson(Lines22 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'kind': instance.kind,
  'name': instance.name,
  'unit_price': instance.unitPrice,
  'quantity': instance.quantity,
  'persons': instance.persons,
  'amount': instance.amount,
};
