// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qr5.dart';

part 'data330.g.dart';

@JsonSerializable()
class Data330 {
  const Data330({required this.qr});

  factory Data330.fromJson(Map<String, Object?> json) =>
      _$Data330FromJson(json);

  final Qr5 qr;

  Map<String, Object?> toJson() => _$Data330ToJson(this);
}
