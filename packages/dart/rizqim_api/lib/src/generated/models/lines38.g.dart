// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines38.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines38 _$Lines38FromJson(Map<String, dynamic> json) => Lines38(
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

Map<String, dynamic> _$Lines38ToJson(Lines38 instance) => <String, dynamic>{
  'name': instance.name,
  'qty': instance.qty,
  'unit': instance.unit,
  'price': instance.price,
  'total': instance.total,
  'modifiers': instance.modifiers,
  'note': instance.note,
};
