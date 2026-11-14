// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data238.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data238 _$Data238FromJson(Map<String, dynamic> json) => Data238(
  event: Event10.fromJson(json['event'] as Map<String, dynamic>),
  guests: Guests4.fromJson(json['guests'] as Map<String, dynamic>),
  lines: (json['lines'] as List<dynamic>)
      .map((e) => Lines24.fromJson(e as Map<String, dynamic>))
      .toList(),
  packageTotal: json['package_total'] as String,
  hallTotal: json['hall_total'] as String,
  extrasTotal: json['extras_total'] as String,
  orders: Orders4.fromJson(json['orders'] as Map<String, dynamic>),
  total: json['total'] as String,
  advances: Advances3.fromJson(json['advances'] as Map<String, dynamic>),
  due: json['due'] as String,
  excess: json['excess'] as String,
  settlement: json['settlement'] == null
      ? null
      : Settlement5.fromJson(json['settlement'] as Map<String, dynamic>),
  warnings: (json['warnings'] as List<dynamic>)
      .map((e) => Warnings18.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data238ToJson(Data238 instance) => <String, dynamic>{
  'event': instance.event,
  'guests': instance.guests,
  'lines': instance.lines,
  'package_total': instance.packageTotal,
  'hall_total': instance.hallTotal,
  'extras_total': instance.extrasTotal,
  'orders': instance.orders,
  'total': instance.total,
  'advances': instance.advances,
  'due': instance.due,
  'excess': instance.excess,
  'settlement': instance.settlement,
  'warnings': instance.warnings,
};
