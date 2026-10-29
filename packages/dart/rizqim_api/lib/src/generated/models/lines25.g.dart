// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines25.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines25 _$Lines25FromJson(Map<String, dynamic> json) => Lines25(
  name: json['name'] as String,
  qty: json['qty'] as String,
  unit: json['unit'] as String?,
  price: json['price'] as String,
  total: json['total'] as String,
  modifiers: (json['modifiers'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  note: json['note'] as String?,
);

Map<String, dynamic> _$Lines25ToJson(Lines25 instance) => <String, dynamic>{
  'name': instance.name,
  'qty': instance.qty,
  'unit': instance.unit,
  'price': instance.price,
  'total': instance.total,
  'modifiers': instance.modifiers,
  'note': instance.note,
};
