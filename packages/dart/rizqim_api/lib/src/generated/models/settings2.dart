// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'settings2.g.dart';

@JsonSerializable()
class Settings2 {
  const Settings2({
    required this.branchId,
    required this.enabled,
    required this.providers,
    required this.defaultProvider,
    required this.expiryMinutes,
    required this.updatedAt,
  });

  factory Settings2.fromJson(Map<String, Object?> json) =>
      _$Settings2FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final bool enabled;
  final List<String> providers;
  @JsonKey(name: 'default_provider')
  final String? defaultProvider;
  @JsonKey(name: 'expiry_minutes')
  final int expiryMinutes;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  Map<String, Object?> toJson() => _$Settings2ToJson(this);
}
