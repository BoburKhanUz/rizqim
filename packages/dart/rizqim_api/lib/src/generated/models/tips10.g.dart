// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tips10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Tips10 _$Tips10FromJson(Map<String, dynamic> json) => Tips10(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  byMethod: (json['by_method'] as List<dynamic>)
      .map((e) => ByMethod5.fromJson(e as Map<String, dynamic>))
      .toList(),
  personal: json['personal'] as String,
  pool: json['pool'] as String,
  unassigned: json['unassigned'] as String,
  byStaff: (json['by_staff'] as List<dynamic>)
      .map((e) => ByStaff4.fromJson(e as Map<String, dynamic>))
      .toList(),
  corrections: Corrections11.fromJson(
    json['corrections'] as Map<String, dynamic>,
  ),
);

Map<String, dynamic> _$Tips10ToJson(Tips10 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'by_method': instance.byMethod,
  'personal': instance.personal,
  'pool': instance.pool,
  'unassigned': instance.unassigned,
  'by_staff': instance.byStaff,
  'corrections': instance.corrections,
};
