// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qr.dart';

part 'data255.g.dart';

@JsonSerializable()
class Data255 {
  const Data255({required this.qr});

  factory Data255.fromJson(Map<String, Object?> json) =>
      _$Data255FromJson(json);

  final Qr qr;

  Map<String, Object?> toJson() => _$Data255ToJson(this);
}
