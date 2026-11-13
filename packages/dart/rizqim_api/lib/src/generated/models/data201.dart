// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qr.dart';

part 'data201.g.dart';

@JsonSerializable()
class Data201 {
  const Data201({required this.qr});

  factory Data201.fromJson(Map<String, Object?> json) =>
      _$Data201FromJson(json);

  final Qr qr;

  Map<String, Object?> toJson() => _$Data201ToJson(this);
}
