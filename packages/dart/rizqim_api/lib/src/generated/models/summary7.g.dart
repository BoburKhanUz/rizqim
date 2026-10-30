// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'summary7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Summary7 _$Summary7FromJson(Map<String, dynamic> json) => Summary7(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  byMethod: (json['by_method'] as List<dynamic>)
      .map((e) => ByMethod12.fromJson(e as Map<String, dynamic>))
      .toList(),
  personal: json['personal'] as String,
  pool: json['pool'] as String,
  unassigned: json['unassigned'] as String,
  byStaff: (json['by_staff'] as List<dynamic>)
      .map((e) => ByStaff8.fromJson(e as Map<String, dynamic>))
      .toList(),
  corrections: Corrections15.fromJson(
    json['corrections'] as Map<String, dynamic>,
  ),
);

Map<String, dynamic> _$Summary7ToJson(Summary7 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'by_method': instance.byMethod,
  'personal': instance.personal,
  'pool': instance.pool,
  'unassigned': instance.unassigned,
  'by_staff': instance.byStaff,
  'corrections': instance.corrections,
};
