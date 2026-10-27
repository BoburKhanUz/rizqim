// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data4 _$Data4FromJson(Map<String, dynamic> json) => Data4(
  recoveryCodes: (json['recovery_codes'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Data4ToJson(Data4 instance) => <String, dynamic>{
  'recovery_codes': instance.recoveryCodes,
};
