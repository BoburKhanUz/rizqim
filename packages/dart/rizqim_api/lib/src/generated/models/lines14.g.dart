// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines14.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines14 _$Lines14FromJson(Map<String, dynamic> json) => Lines14(
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

Map<String, dynamic> _$Lines14ToJson(Lines14 instance) => <String, dynamic>{
  'name': instance.name,
  'qty': instance.qty,
  'unit': instance.unit,
  'price': instance.price,
  'total': instance.total,
  'modifiers': instance.modifiers,
  'note': instance.note,
};
