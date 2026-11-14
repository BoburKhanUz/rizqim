// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'bill2.g.dart';

@JsonSerializable()
class Bill2 {
  const Bill2({required this.uuid});

  factory Bill2.fromJson(Map<String, Object?> json) => _$Bill2FromJson(json);

  final String uuid;

  Map<String, Object?> toJson() => _$Bill2ToJson(this);
}
