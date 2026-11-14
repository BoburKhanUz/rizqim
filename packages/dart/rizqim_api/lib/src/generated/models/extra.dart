// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'extra.g.dart';

@JsonSerializable()
class Extra {
  const Extra({required this.mxik, required this.packageCode});

  factory Extra.fromJson(Map<String, Object?> json) => _$ExtraFromJson(json);

  final String? mxik;
  @JsonKey(name: 'package_code')
  final String? packageCode;

  Map<String, Object?> toJson() => _$ExtraToJson(this);
}
