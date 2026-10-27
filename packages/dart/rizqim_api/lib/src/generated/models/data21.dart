// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data21.g.dart';

@JsonSerializable()
class Data21 {
  const Data21({
    required this.role,
    required this.storeId,
    required this.branchId,
    required this.branchScoped,
    required this.permissions,
    required this.deviceUuid,
    required this.timezone,
  });

  factory Data21.fromJson(Map<String, Object?> json) => _$Data21FromJson(json);

  final String role;
  @JsonKey(name: 'store_id')
  final int? storeId;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'branch_scoped')
  final bool branchScoped;
  final List<String> permissions;
  @JsonKey(name: 'device_uuid')
  final String? deviceUuid;
  final String? timezone;

  Map<String, Object?> toJson() => _$Data21ToJson(this);
}
