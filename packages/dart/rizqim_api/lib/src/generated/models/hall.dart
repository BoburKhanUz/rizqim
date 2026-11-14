// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall.g.dart';

@JsonSerializable()
class Hall {
  const Hall({required this.mxik, required this.packageCode});

  factory Hall.fromJson(Map<String, Object?> json) => _$HallFromJson(json);

  final String? mxik;
  @JsonKey(name: 'package_code')
  final String? packageCode;

  Map<String, Object?> toJson() => _$HallToJson(this);
}
