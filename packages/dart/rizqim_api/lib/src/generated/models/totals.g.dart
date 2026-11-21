// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'totals.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Totals _$TotalsFromJson(Map<String, dynamic> json) => Totals(
  operations: (json['operations'] as num).toInt(),
  applied: (json['applied'] as num).toInt(),
  conflict: (json['conflict'] as num).toInt(),
  rejected: (json['rejected'] as num).toInt(),
  duplicate: (json['duplicate'] as num).toInt(),
  skipped: (json['skipped'] as num).toInt(),
  pending: (json['pending'] as num).toInt(),
  renumbered: (json['renumbered'] as num).toInt(),
);

Map<String, dynamic> _$TotalsToJson(Totals instance) => <String, dynamic>{
  'operations': instance.operations,
  'applied': instance.applied,
  'conflict': instance.conflict,
  'rejected': instance.rejected,
  'duplicate': instance.duplicate,
  'skipped': instance.skipped,
  'pending': instance.pending,
  'renumbered': instance.renumbered,
};
