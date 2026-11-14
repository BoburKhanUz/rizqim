// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'orders4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Orders4 _$Orders4FromJson(Map<String, dynamic> json) => Orders4(
  lines: json['lines'] as String,
  discount: json['discount'] as String,
  serviceCharge: json['service_charge'] as String,
  otherCharges: json['other_charges'] as String,
  total: json['total'] as String,
);

Map<String, dynamic> _$Orders4ToJson(Orders4 instance) => <String, dynamic>{
  'lines': instance.lines,
  'discount': instance.discount,
  'service_charge': instance.serviceCharge,
  'other_charges': instance.otherCharges,
  'total': instance.total,
};
