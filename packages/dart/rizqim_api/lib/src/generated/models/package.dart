// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'package.g.dart';

@JsonSerializable()
class Package {
  const Package({required this.mxik, required this.packageCode});

  factory Package.fromJson(Map<String, Object?> json) =>
      _$PackageFromJson(json);

  final String? mxik;
  @JsonKey(name: 'package_code')
  final String? packageCode;

  Map<String, Object?> toJson() => _$PackageToJson(this);
}
