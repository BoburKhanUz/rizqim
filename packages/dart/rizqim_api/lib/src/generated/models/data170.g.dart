// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data170.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data170 _$Data170FromJson(Map<String, dynamic> json) => Data170(
  uuid: json['uuid'] as String,
  branchId: (json['branch_id'] as num).toInt(),
  period: json['period'] as String,
  periodLabel: json['period_label'] as String,
  periodFrom: json['period_from'] as String,
  periodTo: json['period_to'] as String,
  status: json['status'] as String,
  statusLabel: json['status_label'] as String,
  grossTotal: json['gross_total'] as String,
  payableTotal: json['payable_total'] as String,
  paidTotal: json['paid_total'] as String,
  linesCount: (json['lines_count'] as num).toInt(),
  warnings: (json['warnings'] as List<dynamic>)
      .map((e) => Warnings7.fromJson(e as Map<String, dynamic>))
      .toList(),
  computedAt: json['computed_at'] as String?,
  closedAt: json['closed_at'] as String?,
  version: (json['version'] as num).toInt(),
  createdAt: json['created_at'] as String?,
  lines: (json['lines'] as List<dynamic>)
      .map((e) => Lines13.fromJson(e as Map<String, dynamic>))
      .toList(),
  openShifts: (json['open_shifts'] as List<dynamic>)
      .map((e) => OpenShifts6.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data170ToJson(Data170 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'branch_id': instance.branchId,
  'period': instance.period,
  'period_label': instance.periodLabel,
  'period_from': instance.periodFrom,
  'period_to': instance.periodTo,
  'status': instance.status,
  'status_label': instance.statusLabel,
  'gross_total': instance.grossTotal,
  'payable_total': instance.payableTotal,
  'paid_total': instance.paidTotal,
  'lines_count': instance.linesCount,
  'warnings': instance.warnings,
  'computed_at': instance.computedAt,
  'closed_at': instance.closedAt,
  'version': instance.version,
  'created_at': instance.createdAt,
  'lines': instance.lines,
  'open_shifts': instance.openShifts,
};
