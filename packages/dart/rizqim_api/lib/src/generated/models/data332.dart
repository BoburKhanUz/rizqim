// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qr6.dart';

part 'data332.g.dart';

@JsonSerializable()
class Data332 {
  const Data332({required this.qr});

  factory Data332.fromJson(Map<String, Object?> json) =>
      _$Data332FromJson(json);

  final Qr6 qr;

  Map<String, Object?> toJson() => _$Data332ToJson(this);
}
