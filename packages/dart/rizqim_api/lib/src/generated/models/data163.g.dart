// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data163.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data163 _$Data163FromJson(Map<String, dynamic> json) => Data163(
  serverTime: json['server_time'] as String,
  complete: json['complete'] as bool,
  stoppedReason: json['stopped_reason'] as String?,
  file: File.fromJson(json['file'] as Map<String, dynamic>),
  device: Device10.fromJson(json['device'] as Map<String, dynamic>),
  totals: Totals.fromJson(json['totals'] as Map<String, dynamic>),
  results: (json['results'] as List<dynamic>)
      .map((e) => Results.fromJson(e as Map<String, dynamic>))
      .toList(),
  skipped: (json['skipped'] as List<dynamic>)
      .map((e) => Skipped.fromJson(e as Map<String, dynamic>))
      .toList(),
  warnings: (json['warnings'] as List<dynamic>)
      .map((e) => Warnings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data163ToJson(Data163 instance) => <String, dynamic>{
  'server_time': instance.serverTime,
  'complete': instance.complete,
  'stopped_reason': instance.stoppedReason,
  'file': instance.file,
  'device': instance.device,
  'totals': instance.totals,
  'results': instance.results,
  'skipped': instance.skipped,
  'warnings': instance.warnings,
};
