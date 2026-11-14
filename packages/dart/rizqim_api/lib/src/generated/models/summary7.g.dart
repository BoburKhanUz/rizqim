// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'summary7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Summary7 _$Summary7FromJson(Map<String, dynamic> json) => Summary7(
  total: json['total'] as String,
  paid: json['paid'] as String,
  outstanding: json['outstanding'] as String,
  overdue: json['overdue'] as bool,
  overdueAmount: json['overdue_amount'] as String,
  overdueCount: (json['overdue_count'] as num).toInt(),
  nextDue: json['next_due'] == null
      ? null
      : NextDue3.fromJson(json['next_due'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Summary7ToJson(Summary7 instance) => <String, dynamic>{
  'total': instance.total,
  'paid': instance.paid,
  'outstanding': instance.outstanding,
  'overdue': instance.overdue,
  'overdue_amount': instance.overdueAmount,
  'overdue_count': instance.overdueCount,
  'next_due': instance.nextDue,
};
