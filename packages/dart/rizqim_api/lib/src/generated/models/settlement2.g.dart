// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'settlement2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Settlement2 _$Settlement2FromJson(Map<String, dynamic> json) => Settlement2(
  event: Event3.fromJson(json['event'] as Map<String, dynamic>),
  guests: Guests.fromJson(json['guests'] as Map<String, dynamic>),
  lines: (json['lines'] as List<dynamic>)
      .map((e) => Lines20.fromJson(e as Map<String, dynamic>))
      .toList(),
  packageTotal: json['package_total'] as String,
  hallTotal: json['hall_total'] as String,
  extrasTotal: json['extras_total'] as String,
  orders: Orders2.fromJson(json['orders'] as Map<String, dynamic>),
  total: json['total'] as String,
  advances: Advances.fromJson(json['advances'] as Map<String, dynamic>),
  due: json['due'] as String,
  excess: json['excess'] as String,
  settlement: json['settlement'] == null
      ? null
      : Settlement.fromJson(json['settlement'] as Map<String, dynamic>),
  warnings: (json['warnings'] as List<dynamic>)
      .map((e) => Warnings15.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Settlement2ToJson(Settlement2 instance) =>
    <String, dynamic>{
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
