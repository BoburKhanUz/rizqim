// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tips12.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Tips12 _$Tips12FromJson(Map<String, dynamic> json) => Tips12(
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
  corrections: Corrections13.fromJson(
    json['corrections'] as Map<String, dynamic>,
  ),
);

Map<String, dynamic> _$Tips12ToJson(Tips12 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'by_method': instance.byMethod,
  'personal': instance.personal,
  'pool': instance.pool,
  'unassigned': instance.unassigned,
  'by_staff': instance.byStaff,
  'corrections': instance.corrections,
};
