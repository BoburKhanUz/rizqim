// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'summary5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Summary5 _$Summary5FromJson(Map<String, dynamic> json) => Summary5(
  total: json['total'] as String,
  paid: json['paid'] as String,
  outstanding: json['outstanding'] as String,
  overdue: json['overdue'] as bool,
  overdueAmount: json['overdue_amount'] as String,
  overdueCount: (json['overdue_count'] as num).toInt(),
  nextDue: json['next_due'] == null
      ? null
      : NextDue.fromJson(json['next_due'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Summary5ToJson(Summary5 instance) => <String, dynamic>{
  'total': instance.total,
  'paid': instance.paid,
  'outstanding': instance.outstanding,
  'overdue': instance.overdue,
  'overdue_amount': instance.overdueAmount,
  'overdue_count': instance.overdueCount,
  'next_due': instance.nextDue,
};
