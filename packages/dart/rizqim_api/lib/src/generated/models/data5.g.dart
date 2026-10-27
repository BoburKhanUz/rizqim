// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data5 _$Data5FromJson(Map<String, dynamic> json) => Data5(
  secret: json['secret'] as String,
  otpauthUri: json['otpauth_uri'] as String,
  qrSvg: json['qr_svg'] as String,
);

Map<String, dynamic> _$Data5ToJson(Data5 instance) => <String, dynamic>{
  'secret': instance.secret,
  'otpauth_uri': instance.otpauthUri,
  'qr_svg': instance.qrSvg,
};
