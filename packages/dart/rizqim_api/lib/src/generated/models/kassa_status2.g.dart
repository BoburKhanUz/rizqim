// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'kassa_status2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

KassaStatus2 _$KassaStatus2FromJson(Map<String, dynamic> json) => KassaStatus2(
  registered: json['registered'] as bool,
  kassaId: json['kassa_id'] as String?,
  message: json['message'] as String?,
);

Map<String, dynamic> _$KassaStatus2ToJson(KassaStatus2 instance) =>
    <String, dynamic>{
      'registered': instance.registered,
      'kassa_id': instance.kassaId,
      'message': instance.message,
    };
