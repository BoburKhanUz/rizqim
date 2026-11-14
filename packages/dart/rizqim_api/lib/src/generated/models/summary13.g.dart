// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'summary13.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Summary13 _$Summary13FromJson(Map<String, dynamic> json) => Summary13(
  total: (json['total'] as num).toInt(),
  waiting: (json['waiting'] as num).toInt(),
  seated: (json['seated'] as num).toInt(),
  canceled: (json['canceled'] as num).toInt(),
  noShow: (json['no_show'] as num).toInt(),
  partySizeTotal: (json['party_size_total'] as num).toInt(),
  avgQuotedWaitMinutes: (json['avg_quoted_wait_minutes'] as num?)?.toInt(),
  avgSeatedWaitMinutes: (json['avg_seated_wait_minutes'] as num?)?.toInt(),
  longestWaitingMinutes: (json['longest_waiting_minutes'] as num?)?.toInt(),
);

Map<String, dynamic> _$Summary13ToJson(Summary13 instance) => <String, dynamic>{
  'total': instance.total,
  'waiting': instance.waiting,
  'seated': instance.seated,
  'canceled': instance.canceled,
  'no_show': instance.noShow,
  'party_size_total': instance.partySizeTotal,
  'avg_quoted_wait_minutes': instance.avgQuotedWaitMinutes,
  'avg_seated_wait_minutes': instance.avgSeatedWaitMinutes,
  'longest_waiting_minutes': instance.longestWaitingMinutes,
};
