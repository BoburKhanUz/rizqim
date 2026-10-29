// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data219.dart';

part 'get_v1_terminal_staff_response.g.dart';

@JsonSerializable()
class GetV1TerminalStaffResponse {
  const GetV1TerminalStaffResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1TerminalStaffResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1TerminalStaffResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data219> data;

  Map<String, Object?> toJson() => _$GetV1TerminalStaffResponseToJson(this);
}
