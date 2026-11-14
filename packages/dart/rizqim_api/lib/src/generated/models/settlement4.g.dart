// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'settlement4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Settlement4 _$Settlement4FromJson(Map<String, dynamic> json) => Settlement4(
  event: Event9.fromJson(json['event'] as Map<String, dynamic>),
  guests: Guests3.fromJson(json['guests'] as Map<String, dynamic>),
  lines: (json['lines'] as List<dynamic>)
      .map((e) => Lines22.fromJson(e as Map<String, dynamic>))
      .toList(),
  packageTotal: json['package_total'] as String,
  hallTotal: json['hall_total'] as String,
  extrasTotal: json['extras_total'] as String,
  orders: Orders3.fromJson(json['orders'] as Map<String, dynamic>),
  total: json['total'] as String,
  advances: Advances2.fromJson(json['advances'] as Map<String, dynamic>),
  due: json['due'] as String,
  excess: json['excess'] as String,
  settlement: json['settlement'] == null
      ? null
      : Settlement3.fromJson(json['settlement'] as Map<String, dynamic>),
  warnings: (json['warnings'] as List<dynamic>)
      .map((e) => Warnings17.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Settlement4ToJson(Settlement4 instance) =>
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
