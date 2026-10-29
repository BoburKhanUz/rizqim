// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'totals.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Totals _$TotalsFromJson(Map<String, dynamic> json) => Totals(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  byMethod: (json['by_method'] as List<dynamic>)
      .map((e) => ByMethod.fromJson(e as Map<String, dynamic>))
      .toList(),
  personal: json['personal'] as String,
  pool: json['pool'] as String,
  unassigned: json['unassigned'] as String,
  byStaff: (json['by_staff'] as List<dynamic>)
      .map((e) => ByStaff.fromJson(e as Map<String, dynamic>))
      .toList(),
  corrections: Corrections.fromJson(
    json['corrections'] as Map<String, dynamic>,
  ),
);

Map<String, dynamic> _$TotalsToJson(Totals instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'by_method': instance.byMethod,
  'personal': instance.personal,
  'pool': instance.pool,
  'unassigned': instance.unassigned,
  'by_staff': instance.byStaff,
  'corrections': instance.corrections,
};
