// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qr.dart';

part 'data253.g.dart';

@JsonSerializable()
class Data253 {
  const Data253({required this.qr});

  factory Data253.fromJson(Map<String, Object?> json) =>
      _$Data253FromJson(json);

  final Qr qr;

  Map<String, Object?> toJson() => _$Data253ToJson(this);
}
