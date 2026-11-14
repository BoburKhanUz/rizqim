// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tips15.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Tips15 _$Tips15FromJson(Map<String, dynamic> json) => Tips15(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  byMethod: (json['by_method'] as List<dynamic>)
      .map((e) => ByMethod11.fromJson(e as Map<String, dynamic>))
      .toList(),
  personal: json['personal'] as String,
  pool: json['pool'] as String,
  unassigned: json['unassigned'] as String,
  byStaff: (json['by_staff'] as List<dynamic>)
      .map((e) => ByStaff7.fromJson(e as Map<String, dynamic>))
      .toList(),
  corrections: Corrections16.fromJson(
    json['corrections'] as Map<String, dynamic>,
  ),
);

Map<String, dynamic> _$Tips15ToJson(Tips15 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'by_method': instance.byMethod,
  'personal': instance.personal,
  'pool': instance.pool,
  'unassigned': instance.unassigned,
  'by_staff': instance.byStaff,
  'corrections': instance.corrections,
};
