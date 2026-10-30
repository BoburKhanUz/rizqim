// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tips13.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Tips13 _$Tips13FromJson(Map<String, dynamic> json) => Tips13(
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
  corrections: Corrections14.fromJson(
    json['corrections'] as Map<String, dynamic>,
  ),
);

Map<String, dynamic> _$Tips13ToJson(Tips13 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'by_method': instance.byMethod,
  'personal': instance.personal,
  'pool': instance.pool,
  'unassigned': instance.unassigned,
  'by_staff': instance.byStaff,
  'corrections': instance.corrections,
};
