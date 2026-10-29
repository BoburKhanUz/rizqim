// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'kassa_status.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

KassaStatus _$KassaStatusFromJson(Map<String, dynamic> json) => KassaStatus(
  registered: json['registered'] as bool,
  kassaId: json['kassa_id'] as String?,
  message: json['message'] as String?,
);

Map<String, dynamic> _$KassaStatusToJson(KassaStatus instance) =>
    <String, dynamic>{
      'registered': instance.registered,
      'kassa_id': instance.kassaId,
      'message': instance.message,
    };
