// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'sandbox5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Sandbox5 _$Sandbox5FromJson(Map<String, dynamic> json) => Sandbox5(
  latencyMs: (json['latency_ms'] as num?)?.toInt(),
  failAttempts: (json['fail_attempts'] as num?)?.toInt(),
  failureRate: (json['failure_rate'] as num?)?.toInt(),
  reject: json['reject'] as bool?,
);

Map<String, dynamic> _$Sandbox5ToJson(Sandbox5 instance) => <String, dynamic>{
  'latency_ms': instance.latencyMs,
  'fail_attempts': instance.failAttempts,
  'failure_rate': instance.failureRate,
  'reject': instance.reject,
};
