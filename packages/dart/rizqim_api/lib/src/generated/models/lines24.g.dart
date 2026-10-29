// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines24.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines24 _$Lines24FromJson(Map<String, dynamic> json) => Lines24(
  name: json['name'] as String,
  qty: json['qty'] as String,
  price: json['price'] as String,
  total: json['total'] as String,
  unit: json['unit'] as String?,
  modifiers: (json['modifiers'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
  note: json['note'] as String?,
);

Map<String, dynamic> _$Lines24ToJson(Lines24 instance) => <String, dynamic>{
  'name': instance.name,
  'qty': instance.qty,
  'unit': instance.unit,
  'price': instance.price,
  'total': instance.total,
  'modifiers': instance.modifiers,
  'note': instance.note,
};
