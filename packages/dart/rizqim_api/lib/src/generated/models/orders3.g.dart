// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'orders3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Orders3 _$Orders3FromJson(Map<String, dynamic> json) => Orders3(
  lines: json['lines'] as String,
  discount: json['discount'] as String,
  serviceCharge: json['service_charge'] as String,
  otherCharges: json['other_charges'] as String,
  total: json['total'] as String,
);

Map<String, dynamic> _$Orders3ToJson(Orders3 instance) => <String, dynamic>{
  'lines': instance.lines,
  'discount': instance.discount,
  'service_charge': instance.serviceCharge,
  'other_charges': instance.otherCharges,
  'total': instance.total,
};
