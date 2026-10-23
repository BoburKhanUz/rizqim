// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data188.dart';

part 'get_v1_terminal_response.g.dart';

@JsonSerializable()
class GetV1TerminalResponse {
  const GetV1TerminalResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1TerminalResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1TerminalResponseFromJson(json);

  final bool success;
  final String message;
  final Data188 data;

  Map<String, Object?> toJson() => _$GetV1TerminalResponseToJson(this);
}
