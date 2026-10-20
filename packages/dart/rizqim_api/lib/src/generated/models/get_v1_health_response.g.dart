// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_health_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1HealthResponse _$GetV1HealthResponseFromJson(Map<String, dynamic> json) =>
    GetV1HealthResponse(
      status: Status.fromJson(json['status'] as String),
      checks: (json['checks'] as Map<String, dynamic>).map(
        (k, e) => MapEntry(k, Checks.fromJson(e as Map<String, dynamic>)),
      ),
    );

Map<String, dynamic> _$GetV1HealthResponseToJson(
  GetV1HealthResponse instance,
) => <String, dynamic>{'status': instance.status, 'checks': instance.checks};
