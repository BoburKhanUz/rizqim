// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'sandbox3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Sandbox3 _$Sandbox3FromJson(Map<String, dynamic> json) => Sandbox3(
  latencyMs: (json['latency_ms'] as num?)?.toInt(),
  failAttempts: (json['fail_attempts'] as num?)?.toInt(),
  failureRate: (json['failure_rate'] as num?)?.toInt(),
  reject: json['reject'] as bool?,
);

Map<String, dynamic> _$Sandbox3ToJson(Sandbox3 instance) => <String, dynamic>{
  'latency_ms': instance.latencyMs,
  'fail_attempts': instance.failAttempts,
  'failure_rate': instance.failureRate,
  'reject': instance.reject,
};
