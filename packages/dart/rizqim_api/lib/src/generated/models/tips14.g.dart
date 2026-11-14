// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tips14.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Tips14 _$Tips14FromJson(Map<String, dynamic> json) => Tips14(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  byMethod: (json['by_method'] as List<dynamic>)
      .map((e) => ByMethod9.fromJson(e as Map<String, dynamic>))
      .toList(),
  personal: json['personal'] as String,
  pool: json['pool'] as String,
  unassigned: json['unassigned'] as String,
  byStaff: (json['by_staff'] as List<dynamic>)
      .map((e) => ByStaff6.fromJson(e as Map<String, dynamic>))
      .toList(),
  corrections: Corrections15.fromJson(
    json['corrections'] as Map<String, dynamic>,
  ),
);

Map<String, dynamic> _$Tips14ToJson(Tips14 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'by_method': instance.byMethod,
  'personal': instance.personal,
  'pool': instance.pool,
  'unassigned': instance.unassigned,
  'by_staff': instance.byStaff,
  'corrections': instance.corrections,
};
