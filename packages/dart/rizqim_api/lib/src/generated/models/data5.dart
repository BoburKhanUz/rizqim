// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data5.g.dart';

@JsonSerializable()
class Data5 {
  const Data5({
    required this.secret,
    required this.otpauthUri,
    required this.qrSvg,
  });

  factory Data5.fromJson(Map<String, Object?> json) => _$Data5FromJson(json);

  final String secret;
  @JsonKey(name: 'otpauth_uri')
  final String otpauthUri;
  @JsonKey(name: 'qr_svg')
  final String qrSvg;

  Map<String, Object?> toJson() => _$Data5ToJson(this);
}
