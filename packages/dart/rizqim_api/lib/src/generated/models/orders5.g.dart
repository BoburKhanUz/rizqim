// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'orders5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Orders5 _$Orders5FromJson(Map<String, dynamic> json) => Orders5(
  lines: json['lines'] as String,
  discount: json['discount'] as String,
  serviceCharge: json['service_charge'] as String,
  otherCharges: json['other_charges'] as String,
  total: json['total'] as String,
);

Map<String, dynamic> _$Orders5ToJson(Orders5 instance) => <String, dynamic>{
  'lines': instance.lines,
  'discount': instance.discount,
  'service_charge': instance.serviceCharge,
  'other_charges': instance.otherCharges,
  'total': instance.total,
};
