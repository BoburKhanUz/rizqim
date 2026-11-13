// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data231.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data231 _$Data231FromJson(Map<String, dynamic> json) => Data231(
  account: Account4.fromJson(json['account'] as Map<String, dynamic>),
  month: json['month'] as String,
  from: json['from'] as String,
  to: json['to'] as String,
  days: (json['days'] as List<dynamic>).map((e) => e as String).toList(),
  rows: (json['rows'] as List<dynamic>)
      .map((e) => Rows3.fromJson(e as Map<String, dynamic>))
      .toList(),
  dayTotals: (json['day_totals'] as List<dynamic>)
      .map((e) => DayTotals.fromJson(e as Map<String, dynamic>))
      .toList(),
  total: json['total'] as String,
  meals: (json['meals'] as num).toInt(),
  repaidTotal: json['repaid_total'] as String,
  monthlyLimit: json['monthly_limit'] as String?,
  balance: json['balance'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
  masked: json['masked'] as bool,
);

Map<String, dynamic> _$Data231ToJson(Data231 instance) => <String, dynamic>{
  'account': instance.account,
  'month': instance.month,
  'from': instance.from,
  'to': instance.to,
  'days': instance.days,
  'rows': instance.rows,
  'day_totals': instance.dayTotals,
  'total': instance.total,
  'meals': instance.meals,
  'repaid_total': instance.repaidTotal,
  'monthly_limit': instance.monthlyLimit,
  'balance': instance.balance,
  'branch_id': instance.branchId,
  'masked': instance.masked,
};
